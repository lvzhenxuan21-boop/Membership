<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrdersTable
{
    // 订单核销属资金操作，与 API 端管理员权限保持一致
    private static function isAdmin(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin','admin','tenant_admin']) ?? false;
    }

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
            ViewAction::make(), // 详情只读：手改状态/金额会绕过业务钩子
            Action::make('markPaid')->label('标记已付')
                ->visible(fn(Order $r)=> $r->status==='pending' && self::isAdmin())
                ->requiresConfirmation()
                ->action(function (Order $record) {
                    // 优先走关联支付单（业务钩子：销量/订阅/券核销都在那里面）
                    $payment = \App\Models\Payment::where('order_no', $record->payment_order_no)->first();
                    try {
                        if ($payment) {
                            app(PaymentService::class)->markPaid($payment);
                            return;
                        }
                        // 无支付单的兜底：手动复刻核销语义（销量按购买数量累计）
                        DB::transaction(function () use ($record) {
                            $record->update(['status'=>'paid','paid_at'=>now()]);
                            foreach ($record->items as $item) {
                                \App\Models\Product::where('id', $item->product_id)->increment('sales', $item->quantity);
                            }
                        });
                    } catch (\Throwable $e) {
                        Log::warning('[Filament] 订单核销失败', ['order_no'=>$record->order_no, 'error'=>$e->getMessage()]);
                        \Filament\Notifications\Notification::make()->title('核销失败：'.$e->getMessage())->danger()->send();
                    }
                }),
            Action::make('ship')->label('发货')->visible(fn(Order $r)=> $r->status==='paid')->action(fn(Order $r)=> $r->update(['status'=>'shipped'])),
            Action::make('complete')->label('完成')->visible(fn(Order $r)=> $r->status==='shipped')->requiresConfirmation()->action(fn(Order $r)=> $r->update(['status'=>'completed'])),
        ]);
    }
}
