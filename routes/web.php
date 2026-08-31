<?php

use App\Http\Controllers\Web\ShopController;
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
    Auth::login($u, true);
    return redirect('/')->with('success','注册成功，已自动成为普通会员');
})->middleware('throttle:5,1')->name('web.register.post');

Route::post('/logout', function(Request $r){ Auth::logout(); $r->session()->invalidate(); $r->session()->regenerateToken(); return redirect('/'); })->name('web.logout');

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
    $tenant = \Illuminate\Support\Facades\DB::transaction(function() use ($data, $user, $ownAccount){
        $slug = \Illuminate\Support\Str::slug($data['slug']);
        $domain = $slug.'.'. (function(){ $url=config('app.url','http://xxx.com'); $h=parse_url($url,PHP_URL_HOST)?:'xxx.com'; $h=preg_replace('/^www\./','',$h); if($h==='localhost'||str_contains($h,'127.0.0.1')) return 'xxx.com'; return $h; })();
        $t = \App\Models\Tenant::create(['name'=>$data['tenant_name'],'slug'=>$slug,'contact_name'=>$ownAccount ? $user->name : $data['admin_name'],'status'=>'active','settings'=>['domain'=>$domain,'platform_fee_rate'=>0.05]]);
        \App\Models\Branch::create(['tenant_id'=>$t->id,'name'=>'总店','status'=>'active']);
        \App\Models\MembershipLevel::create(['tenant_id'=>$t->id,'name'=>'普通会员','slug'=>'normal','level'=>1,'min_points'=>0,'is_default'=>true]);
        if ($ownAccount) {
            $u = $user; // 用当前账号，无需另建管理员
        } else {
            $u = \App\Models\User::create(['name'=>$data['admin_name'],'email'=>$data['admin_email'],'password'=>Hash::make($data['admin_password']),'tenant_id'=>$t->id]);
        }
        $u->update(['tenant_id'=>$t->id]);
        try{ $u->assignRole('tenant_admin'); }catch(\Throwable $e){}
        return $t;
    });
    $msg = '创建成功：'.$tenant->slug.' → '.$tenant->settings['domain'];
    return redirect('/shop/'.$tenant->slug)->with('success', $ownAccount ? $msg.'，当前账号已成为店铺管理员，可直接登录 /admin 管理' : $msg.'，请用管理员账号登录 /admin');
})->middleware('throttle:3,1')->name('web.tenants.store');

// 商城前台（tenant 自动解析）
Route::get('/pricing', [ShopController::class,'pricing'])->name('web.pricing');
Route::post('/pricing/subscribe', [ShopController::class,'subscribePlan'])->name('web.pricing.subscribe');
Route::get('/pay/{orderNo}', [ShopController::class,'payShow'])->name('web.pay.show');
Route::post('/pay/{orderNo}/switch-channel', [ShopController::class,'switchChannel'])->name('web.pay.switch');
Route::get('/me', [ShopController::class,'me'])->name('web.me');
Route::post('/me', [ShopController::class,'updateMe'])->name('web.me.update');
Route::get('/check-in', [ShopController::class,'checkInPage'])->name('web.checkin');
Route::post('/check-in', [ShopController::class,'checkIn'])->name('web.checkin.store');
Route::get('/products/{id}', [ShopController::class,'show'])->name('web.products.show');
Route::get('/cart', [ShopController::class,'cart'])->name('web.cart');
Route::get('/checkout', [ShopController::class,'checkout'])->name('web.checkout');
Route::post('/checkout', [ShopController::class,'placeOrder'])->name('web.checkout.place');
Route::get('/orders', [ShopController::class,'orders'])->name('web.orders');
Route::get('/orders/{orderNo}', [ShopController::class,'orderShow'])->name('web.orders.show');
Route::get('/shop/{slug}', [ShopController::class,'index'])->name('web.shop.slug');
Route::get('/', [ShopController::class,'index'])->name('web.home');
