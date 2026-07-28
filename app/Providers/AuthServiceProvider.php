<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\LabelLog;
use App\Models\Product;
use App\Models\ProductModel;
use App\Models\TechnicalComposition;
use App\Models\User;
use App\Models\Warranty;
use App\Models\ZebraPrintSetting;
use App\Policies\CategoryPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\LabelBatchPolicy;
use App\Policies\LabelLogPolicy;
use App\Policies\LabelPolicy;
use App\Policies\ProductModelPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RolePolicy;
use App\Policies\TechnicalCompositionPolicy;
use App\Policies\UserPolicy;
use App\Policies\WarrantyPolicy;
use App\Policies\ZebraPrintSettingPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Spatie\Permission\Models\Role;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Category::class => CategoryPolicy::class,
        Customer::class => CustomerPolicy::class,
        Label::class => LabelPolicy::class,
        LabelBatch::class => LabelBatchPolicy::class,
        LabelLog::class => LabelLogPolicy::class,
        Product::class => ProductPolicy::class,
        ProductModel::class => ProductModelPolicy::class,
        Role::class => RolePolicy::class,
        TechnicalComposition::class => TechnicalCompositionPolicy::class,
        User::class => UserPolicy::class,
        Warranty::class => WarrantyPolicy::class,
        ZebraPrintSetting::class => ZebraPrintSettingPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
