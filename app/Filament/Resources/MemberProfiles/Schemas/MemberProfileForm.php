<?php

namespace App\Filament\Resources\MemberProfiles\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MemberProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Select::make('tenant_id')
                    ->relationship('tenant', 'name')
                    ->required(),
                Select::make('branch_id')
                    ->relationship('branch', 'name'),
                Select::make('membership_level_id')
                    ->relationship('level', 'name')
                    ->label('会员等级')
                    ->preload(),
                TextInput::make('member_no')
                    ->required()->label('会员编号')->unique(ignoreRecord:true),
                TextInput::make('real_name')->label('姓名'),
                TextInput::make('phone')
                    ->tel()->label('手机号'),
                TextInput::make('id_card')->label('身份证'),
                DatePicker::make('birthday')->label('生日'),
                Select::make('gender')
                    ->options(['unknown'=>'未知','male'=>'男','female'=>'女'])
                    ->required()->default('unknown')->label('性别'),
                TextInput::make('avatar')->label('头像'),
                TextInput::make('points')
                    ->required()->numeric()->default(0)->label('积分'),
                TextInput::make('growth')
                    ->required()->numeric()->default(0)->label('成长值'),
                TextInput::make('balance')
                    ->required()->numeric()->default(0)->label('余额'),
                TextInput::make('total_orders')
                    ->required()->numeric()->default(0)->label('订单数'),
                TextInput::make('total_spent')
                    ->required()->numeric()->default(0)->label('累计消费'),
                DateTimePicker::make('joined_at')->label('入会时间'),
                DateTimePicker::make('last_active_at')->label('最后活跃'),
                Select::make('status')
                    ->options(['active'=>'正常','frozen'=>'冻结','cancelled'=>'注销'])
                    ->required()->default('active')->label('状态'),
                Textarea::make('extra')
                    ->columnSpanFull(),
            ]);
    }
}
