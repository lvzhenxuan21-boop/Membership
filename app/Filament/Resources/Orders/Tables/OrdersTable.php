<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order_no')->searchable()->label('订单号')->copyable(),
            TextColumn::make('tenant.name')->label('商户')->toggleable(),
            TextColumn::make('shop.name')->label('店铺')->searchable(),
            TextColumn::make('user.name')->label('客户')->searchable(),
            TextColumn::make('pay_amount')->money('CNY')->sortable()->label('实付'),
            TextColumn::make('platform_fee')->money('CNY')->label('抽佣'),
            TextColumn::make('status')->badge()->color(fn($s)=> match($s){'paid'=>'success','pending'=>'warning','shipped'=>'info','completed'=>'success','cancelled'=>'gray','refunded'=>'danger',default=>'gray'}),
            TextColumn::make('payment_channel')->label('渠道'),
            TextColumn::make('paid_at')->dateTime()->sortable(),
            TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault:true),
        ])->filters([])->recordActions([
            EditAction::make(),
            Action::make('markPaid')->label('标记已付')->visible(fn(Order $r)=> $r->status==='pending')->requiresConfirmation()->action(function (Order $record) {
                // 通过关联 Payment 标记
                $payment = \App\Models\Payment::where('order_no', $record->payment_order_no)->first();
                if ($payment) app(PaymentService::class)->markPaid($payment);
                else {
                    DB::transaction(function () use ($record) {
                        $record->update(['status'=>'paid','paid_at'=>now()]);
                        $record->shop->products()->whereIn('id', $record->items->pluck('product_id'))->increment('sales', 1);
                    });
                }
            }),
            Action::make('ship')->label('发货')->visible(fn(Order $r)=> $r->status==='paid')->action(fn(Order $r)=> $r->update(['status'=>'shipped'])),
        ]);
    }
}
