@extends('layouts.shop')
@section('title','创建店铺')
@section('content')
<div class="max-w-lg mx-auto rounded-[24px] bg-white border border-[var(--line)] overflow-hidden soft">
    <div class="px-6 pt-6 pb-4 border-b border-[var(--line)]">
        <div class="mono text-[11px] tracking-[0.18em] opacity-40">NEW STORE</div>
        <h1 class="serif text-2xl tracking-[-0.02em] mt-1">创建店铺</h1>
        <p class="mono text-xs opacity-50 mt-1">生成 shop slug → shop1.xxx.com 独立商城</p>
    </div>
    <form method="POST" action="{{ route('web.tenants.store') }}" class="p-6 space-y-4">
        @csrf
        <label class="block mono text-xs">商户名称<input name="tenant_name" placeholder="例如：夏日海边小店" class="mt-1 w-full h-10 px-3 rounded-full border border-[var(--line)] bg-[var(--paper2)] mono text-sm focus:bg-white focus:outline-none" required></label>
        <label class="block mono text-xs">店网址 slug
            <div class="mt-1 flex gap-2">
                <input name="slug" placeholder="如 summer" pattern="[a-z0-9-]+" class="flex-1 h-10 px-4 rounded-full border border-[var(--line)] mono text-sm font-medium focus:outline-none focus:border-[var(--ink)]/20" required>
                <button type="button" onclick="checkSlug()" class="h-10 px-4 rounded-full border border-[var(--line)] bg-[var(--paper2)] mono text-xs font-semibold hover:bg-white">查重</button>
            </div>
            <div id="slugHint" class="mono text-xs mt-1"></div>
            <div class="mono text-xs opacity-40">将生成：<span id="slugPreview" class="font-semibold text-[var(--ink)] opacity-100">summer.xxx.com</span></div>
        </label>
        <label class="block mono text-xs">管理员姓名<input name="admin_name" placeholder="管理员姓名" class="mt-1 w-full h-10 px-3 rounded-full border border-[var(--line)] mono text-sm" required></label>
        <label class="block mono text-xs">管理员邮箱<input name="admin_email" type="email" placeholder="admin@example.com" class="mt-1 w-full h-10 px-3 rounded-full border border-[var(--line)] mono text-sm" required></label>
        <label class="block mono text-xs">密码<input name="admin_password" type="password" placeholder="≥8位" class="mt-1 w-full h-10 px-3 rounded-full border border-[var(--line)] mono text-sm" required>
            <input name="admin_password_confirmation" type="password" placeholder="确认密码" class="mt-2 w-full h-10 px-3 rounded-full border border-[var(--line)] mono text-sm" required></label>
        <button class="w-full h-11 rounded-full bg-[var(--ink)] text-white font-semibold hover:bg-black">创建并跳转 →</button>
    </form>
</div>
@push('scripts')
<script>
document.querySelector('input[name=slug]')?.addEventListener('input', e=>{
    let v=e.target.value.toLowerCase().replace(/[^a-z0-9-]/g,''); e.target.value=v;
    document.getElementById('slugPreview').textContent=(v||'shop1')+'.xxx.com';
});
function checkSlug(){
    let slug=document.querySelector('input[name=slug]').value.trim();
    if(!slug) return;
    fetch('/api/v1/tenants/check-slug?slug='+encodeURIComponent(slug)).then(r=>r.json()).then(j=>{
        let el=document.getElementById('slugHint');
        el.textContent = j.available ? '✔ 可用 — '+slug+'.xxx.com' : '✘ 已被占用';
        el.className = j.available ? 'mono text-xs text-emerald-600' : 'mono text-xs text-red-600';
    })
}
</script>
@endpush
@endsection
