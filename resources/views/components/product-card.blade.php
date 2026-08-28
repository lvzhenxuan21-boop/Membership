@props(['product'])
<a href="{{ route('web.products.show', $product->id) }}" class="group bg-white rounded-[20px] border border-[var(--line)] overflow-hidden flex flex-col soft soft-hover lift">
    <div class="aspect-[4/3] bg-[var(--paper2)] overflow-hidden relative">
        @if($product->cover)
            <img src="{{ $product->cover }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-[1.04] transition duration-700">
        @else
            <div class="w-full h-full grid place-items-center mono text-xs opacity-30">NO IMAGE</div>
        @endif
        @if($product->stock < 5 && $product->stock>0)
            <span class="absolute top-3 left-3 mono text-[11px] font-semibold px-2.5 py-1 rounded-full bg-amber-500 text-white shadow">仅剩 {{ $product->stock }} 件</span>
        @endif
        @if($product->stock==0)
            <span class="absolute inset-0 grid place-items-center bg-white/80 backdrop-blur-[1px] mono text-xs font-semibold tracking-widest">已售罄 · SOLD OUT</span>
        @endif
        <span class="absolute bottom-3 right-3 mono text-[11px] px-2 py-1 rounded-full bg-white/90 backdrop-blur border border-[var(--line)]">销量 {{ $product->sales }}</span>
    </div>
    <div class="p-4 flex flex-col flex-1">
        <div class="mono text-[11px] tracking-wide opacity-40 truncate">{{ $product->shop->name ?? '' }} · #{{ str_pad($product->id,4,'0',STR_PAD_LEFT) }}</div>
        <div class="font-medium leading-tight line-clamp-2 min-h-[2.6rem] mt-1 text-[14px]">{{ $product->name }}</div>
        <div class="mt-3 flex items-baseline gap-2">
            <span class="serif text-[22px] leading-none tracking-tight">¥{{ rtrim(rtrim(number_format($product->price,2),'0'),'.') }}</span>
            @if($product->original_price && $product->original_price > $product->price)
                <span class="mono text-xs line-through opacity-30">¥{{ rtrim(rtrim(number_format($product->original_price,2),'0'),'.') }}</span>
            @endif
            <span class="ml-auto mono text-[11px] px-2 py-1 rounded-full bg-[var(--paper2)] border border-[var(--line)] opacity-60">库存 {{ $product->stock }}</span>
        </div>
    </div>
</a>
