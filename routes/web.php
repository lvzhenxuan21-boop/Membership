<?php

use App\Http\Controllers\Web\ShopController;
use App\Http\Controllers\Web\VerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Api\TenantAuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// 认证
Route::get('/login', fn(Request $r)=> view('auth.login', ['tenant'=>$r->attributes->get('tenant')]))->name('web.login');
Route::post('/login', function(Request $r){
    $d=$r->validate(['email'=>'required|email','password'=>'required']);
    if(Auth::attempt($d, true)){ $r->session()->regenerate(); return redirect()->intended('/'); }
    return back()->withErrors(['email'=>'账号或密码错误'])->withInput();
})->middleware('throttle:10,1')->name('web.login.post');

Route::get('/register', fn(Request $r)=> view('auth.register', ['tenant'=>$r->attributes->get('tenant')]))->name('web.register');
Route::post('/register', function(Request $r){
    $d=$r->validate([
        'name'=>'required|string|max:50',
        'email'=>'required|email|unique:users,email',
        // 与 API 端注册保持一致强度：至少 8 位且含大小写字母与数字
        'password'=>['required','confirmed',\Illuminate\Validation\Rules\Password::min(8)->letters()->mixedCase()->numbers()],
    ]);
    $tenant = $r->attributes->get('tenant') ?? \App\Http\Middleware\ResolveTenant::resolve($r) ?? \App\Models\Tenant::where('slug','demo')->first() ?? \App\Models\Tenant::first();
    if(!$tenant) return back()->withErrors(['email'=>'系统未初始化商户，请先创建 demo 商户'])->withInput();
    $u = \Illuminate\Support\Facades\DB::transaction(function() use ($d, $tenant){
        $user = User::create(['name'=>$d['name'],'email'=>$d['email'],'password'=>Hash::make($d['password'])]);
        $user->assignRole('member');
        $level = \App\Models\MembershipLevel::where('tenant_id',$tenant->id)->where('is_default',true)->first()
            ?? \App\Models\MembershipLevel::where('tenant_id',$tenant->id)->orderBy('level')->first();
        $branchId = \App\Models\Branch::where('tenant_id',$tenant->id)->value('id');
        \App\Models\MemberProfile::create([
            'user_id'=>$user->id,
            'tenant_id'=>$tenant->id,
            'branch_id'=>$branchId,
            'membership_level_id'=>$level?->id,
            'status'=>'active',
        ]);
        \App\Models\Wallet::firstOrCreate(['tenant_id'=>$tenant->id,'user_id'=>$user->id], ['balance'=>0]);
        return $user;
    });
    if (config('membership.email_verification')) {
        $u->sendEmailVerificationNotification();
    }
    Auth::login($u, true);
    return redirect('/')->with('success', config('membership.email_verification')
        ? '注册成功，请先到邮箱完成验证再下单'
        : '注册成功，已自动成为普通会员');
})->middleware('throttle:5,1')->name('web.register.post');

Route::post('/logout', function(Request $r){ Auth::logout(); $r->session()->invalidate(); $r->session()->regenerateToken(); return redirect('/'); })->name('web.logout');

// 密码找回（Web 表单 + 邮件重置链接；API 端点在 routes/api.php）
Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('web.password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('web.password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('web.password.update');

// 邮箱验证（仅 MEMBERSHIP_EMAIL_VERIFICATION=true 时强制，见 EnsureEmailVerifiedIfRequired）
Route::get('/email/verify', [VerificationController::class, 'notice'])->middleware('auth')->name('web.verification.notice');
Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['auth', 'signed'])->name('verification.verify');
Route::post('/email/verification-notification', [VerificationController::class, 'resend'])->middleware(['auth', 'throttle:6,1'])->name('web.verification.send');

// B端入驻表单
Route::get('/tenants/register', function(Request $r){
    $user = Auth::user();
    // 已登录、不归属任何租户、非平台管理员 → 可用当前账号直接开店
    $ownAccount = $user && !$user->tenant_id && !$user->hasAnyRole(['super_admin','admin']);
    return view('tenants.register', [
        'tenant' => $r->attributes->get('tenant'),
        'ownAccount' => $ownAccount,
        'userName' => $user?->name,
        'hasOwnShop' => (bool)($user?->tenant_id),
    ]);
})->name('web.tenants.register');
Route::post('/tenants/register', function(Request $r){
    $user = Auth::user();
    $ownAccount = $user && !$user->tenant_id && !$user->hasAnyRole(['super_admin','admin']);
    if ($ownAccount) {
        $data = $r->validate([
            'tenant_name'=>'required|string|max:100',
            'slug'=>'required|string|max:50|regex:/^[a-z0-9-]+$/|unique:tenants,slug',
        ]);
    } else {
        $data = $r->validate([
            'tenant_name'=>'required|string|max:100',
            'slug'=>'required|string|max:50|regex:/^[a-z0-9-]+$/|unique:tenants,slug',
            'admin_name'=>'required|string|max:50',
            'admin_email'=>'required|email|unique:users,email',
            'admin_password'=>['required','confirmed',\Illuminate\Validation\Rules\Password::min(8)->letters()->mixedCase()->numbers()],
            'admin_password_confirmation'=>'required',
        ]);
    }

    // 开通逻辑与 API 共用 TenantProvisioning（是否直接激活由 MEMBERSHIP_TENANT_AUTO_ACTIVATE 控制）
    [$tenant, $admin] = app(\App\Services\TenantProvisioning::class)->provision(
        $data['tenant_name'],
        $data['slug'],
        $ownAccount ? $user->name : $data['admin_name'],
        $ownAccount ? $user : null,
        $ownAccount ? null : ['name'=>$data['admin_name'],'email'=>$data['admin_email'],'password'=>$data['admin_password']],
    );

    if ($tenant->status !== 'active') {
        return redirect('/')->with('success', '店铺「'.$tenant->name.'」创建成功，等待平台审核通过后即可访问');
    }
    $msg = '创建成功：'.$tenant->slug.' → '.($tenant->settings['domain'] ?? '');
    return redirect('/shop/'.$tenant->slug)->with('success', $ownAccount ? $msg.'，当前账号已成为店铺管理员，可直接登录 /admin 管理' : $msg.'，请用管理员账号登录 /admin');
})->middleware('throttle:3,1')->name('web.tenants.store');

// 商城前台（tenant 自动解析）
Route::get('/pricing', [ShopController::class,'pricing'])->name('web.pricing');
Route::post('/pricing/subscribe', [ShopController::class,'subscribePlan'])->middleware('verify-email')->name('web.pricing.subscribe');
Route::get('/pay/{orderNo}', [ShopController::class,'payShow'])->name('web.pay.show');
Route::post('/pay/{orderNo}/switch-channel', [ShopController::class,'switchChannel'])->name('web.pay.switch');
Route::get('/me', [ShopController::class,'me'])->name('web.me');
Route::post('/me', [ShopController::class,'updateMe'])->name('web.me.update');
Route::get('/check-in', [ShopController::class,'checkInPage'])->name('web.checkin');
Route::post('/check-in', [ShopController::class,'checkIn'])->middleware('verify-email')->name('web.checkin.store');
Route::get('/products/{id}', [ShopController::class,'show'])->name('web.products.show');
Route::get('/cart', [ShopController::class,'cart'])->name('web.cart');
Route::get('/checkout', [ShopController::class,'checkout'])->name('web.checkout');
Route::post('/checkout', [ShopController::class,'placeOrder'])->middleware(['throttle:10,1', 'verify-email'])->name('web.checkout.place');
Route::get('/orders', [ShopController::class,'orders'])->name('web.orders');
Route::get('/orders/{orderNo}', [ShopController::class,'orderShow'])->name('web.orders.show');
Route::get('/shop/{slug}', [ShopController::class,'index'])->name('web.shop.slug');
Route::get('/', [ShopController::class,'index'])->name('web.home');
