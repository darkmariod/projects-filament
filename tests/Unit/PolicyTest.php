<?php

declare(strict_types=1);

namespace Tests\Unit;

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
use App\Providers\AuthServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $userWithPermission;
    private User $userWithoutPermission;

    protected function setUp(): void
    {
        parent::setUp();

        $roleWith = Role::create(['name' => 'test-role', 'guard_name' => 'web']);
        $roleWithout = Role::create(['name' => 'test-role-no-perms', 'guard_name' => 'web']);

        $this->userWithPermission = User::factory()->create();
        $this->userWithPermission->assignRole($roleWith);

        $this->userWithoutPermission = User::factory()->create();
        $this->userWithoutPermission->assignRole($roleWithout);
    }

    private function createPermission(string $name): void
    {
        Permission::create(['name' => $name, 'guard_name' => 'web']);
        $this->userWithPermission->givePermissionTo($name);
    }

    private static function stdTests(string $policyClass, string $modelClass, string $viewPerm, string $createPerm, string $editPerm, string $deletePerm): array
    {
        return [$policyClass, $modelClass, $viewPerm, $createPerm, $editPerm, $deletePerm];
    }

    /**
     * @dataProvider standardCrudPoliciesProvider
     */
    public function test_standard_crud_policy(
        string $policyClass,
        string $modelClass,
        string $viewPerm,
        string $createPerm,
        string $editPerm,
        string $deletePerm,
    ): void {
        $this->createPermission($viewPerm);
        $this->createPermission($createPerm);
        $this->createPermission($editPerm);
        $this->createPermission($deletePerm);

        /** @var \App\Policies\BasePolicy $policy */
        $policy = app($policyClass);
        $record = new $modelClass;

        $this->assertTrue($policy->viewAny($this->userWithPermission));
        $this->assertFalse($policy->viewAny($this->userWithoutPermission));
        $this->assertTrue($policy->view($this->userWithPermission, $record));
        $this->assertFalse($policy->view($this->userWithoutPermission, $record));
        $this->assertTrue($policy->create($this->userWithPermission));
        $this->assertFalse($policy->create($this->userWithoutPermission));
        $this->assertTrue($policy->update($this->userWithPermission, $record));
        $this->assertFalse($policy->update($this->userWithoutPermission, $record));
        $this->assertTrue($policy->delete($this->userWithPermission, $record));
        $this->assertFalse($policy->delete($this->userWithoutPermission, $record));
    }

    public static function standardCrudPoliciesProvider(): array
    {
        return [
            'User'              => self::stdTests(UserPolicy::class, User::class, 'ver usuarios', 'crear usuarios', 'editar usuarios', 'eliminar usuarios'),
            'Category'          => self::stdTests(CategoryPolicy::class, Category::class, 'ver categorias', 'crear categorias', 'editar categorias', 'eliminar categorias'),
            'ProductModel'      => self::stdTests(ProductModelPolicy::class, ProductModel::class, 'ver modelos', 'crear modelos', 'editar modelos', 'eliminar modelos'),
            'Product'           => self::stdTests(ProductPolicy::class, Product::class, 'ver productos', 'crear productos', 'editar productos', 'eliminar productos'),
            'TechnicalComposition' => self::stdTests(TechnicalCompositionPolicy::class, TechnicalComposition::class, 'ver composiciones', 'crear composiciones', 'editar composiciones', 'eliminar composiciones'),
            'ZebraPrintSetting' => self::stdTests(ZebraPrintSettingPolicy::class, ZebraPrintSetting::class, 'ver configuracion zebra', 'crear configuracion zebra', 'editar configuracion zebra', 'eliminar configuracion zebra'),
        ];
    }

    // ── RolePolicy ──────────────────────────────────────────────────

    public function test_role_policy_standard_crud(): void
    {
        $this->createPermission('ver roles');
        $this->createPermission('crear roles');
        $this->createPermission('editar roles');
        $this->createPermission('eliminar roles');

        $policy = app(RolePolicy::class);
        $record = new Role;

        $this->assertTrue($policy->viewAny($this->userWithPermission));
        $this->assertTrue($policy->create($this->userWithPermission));
        $this->assertTrue($policy->update($this->userWithPermission, $record));
        $this->assertTrue($policy->delete($this->userWithPermission, $record));
        $this->assertFalse($policy->viewAny($this->userWithoutPermission));
    }

    public function test_role_policy_assign_permissions(): void
    {
        $this->createPermission('asignar permisos');
        $policy = app(RolePolicy::class);

        $this->assertTrue($policy->assignPermissions($this->userWithPermission));
        $this->assertFalse($policy->assignPermissions($this->userWithoutPermission));
    }

    // ── LabelBatchPolicy ────────────────────────────────────────────

    public function test_label_batch_policy_anomalies(): void
    {
        $this->createPermission('ver lotes');
        $policy = app(LabelBatchPolicy::class);
        $record = new LabelBatch;

        $this->assertTrue($policy->viewAny($this->userWithPermission));
        $this->assertTrue($policy->view($this->userWithPermission, $record));
        $this->assertTrue($policy->update($this->userWithPermission, $record)); // maps to 'ver lotes'
        $this->assertFalse($policy->update($this->userWithoutPermission, $record));
    }

    public function test_label_batch_policy_delete_use_anular_lotes(): void
    {
        $this->createPermission('anular lotes');
        $policy = app(LabelBatchPolicy::class);
        $record = new LabelBatch;

        $this->assertTrue($policy->delete($this->userWithPermission, $record));
        $this->assertFalse($policy->delete($this->userWithoutPermission, $record));
    }

    public function test_label_batch_policy_generate_labels(): void
    {
        $this->createPermission('generar etiquetas');
        $policy = app(LabelBatchPolicy::class);

        $this->assertTrue($policy->generateLabels($this->userWithPermission));
        $this->assertFalse($policy->generateLabels($this->userWithoutPermission));
    }

    public function test_label_batch_policy_downloads_map_to_ver_etiquetas(): void
    {
        $this->createPermission('ver etiquetas');
        $policy = app(LabelBatchPolicy::class);
        $record = new LabelBatch;

        $this->assertTrue($policy->downloadZpl($this->userWithPermission, $record));
        $this->assertTrue($policy->downloadPdf($this->userWithPermission, $record));
        $this->assertFalse($policy->downloadZpl($this->userWithoutPermission, $record));
        $this->assertFalse($policy->downloadPdf($this->userWithoutPermission, $record));
    }

    // ── LabelPolicy ─────────────────────────────────────────────────

    public function test_label_policy_anomalies(): void
    {
        $this->createPermission('ver etiquetas');
        $policy = app(LabelPolicy::class);
        $record = new Label;

        $this->assertTrue($policy->viewAny($this->userWithPermission));
        $this->assertTrue($policy->create($this->userWithPermission)); // maps to 'ver etiquetas'
        $this->assertTrue($policy->update($this->userWithPermission, $record)); // maps to 'ver etiquetas'
        $this->assertFalse($policy->create($this->userWithoutPermission));
        $this->assertFalse($policy->update($this->userWithoutPermission, $record));
    }

    public function test_label_policy_delete_use_anular_etiquetas(): void
    {
        $this->createPermission('anular etiquetas');
        $policy = app(LabelPolicy::class);
        $record = new Label;

        $this->assertTrue($policy->delete($this->userWithPermission, $record));
        $this->assertFalse($policy->delete($this->userWithoutPermission, $record));
    }

    public function test_label_policy_downloads_map_to_ver_etiquetas(): void
    {
        $this->createPermission('ver etiquetas');
        $policy = app(LabelPolicy::class);
        $record = new Label;

        $this->assertTrue($policy->downloadZpl($this->userWithPermission, $record));
        $this->assertTrue($policy->downloadPdf($this->userWithPermission, $record));
        $this->assertFalse($policy->downloadZpl($this->userWithoutPermission, $record));
        $this->assertFalse($policy->downloadPdf($this->userWithoutPermission, $record));
    }

    // ── WarrantyPolicy ──────────────────────────────────────────────

    public function test_warranty_policy_anomalies(): void
    {
        $this->createPermission('ver garantias');
        $policy = app(WarrantyPolicy::class);
        $record = new Warranty;

        $this->assertTrue($policy->viewAny($this->userWithPermission));
        $this->assertTrue($policy->create($this->userWithPermission)); // maps to 'ver garantias'
        $this->assertTrue($policy->update($this->userWithPermission, $record)); // maps to 'ver garantias'
        $this->assertFalse($policy->create($this->userWithoutPermission));
        $this->assertFalse($policy->update($this->userWithoutPermission, $record));
    }

    public function test_warranty_policy_delete_use_anular_garantias(): void
    {
        $this->createPermission('anular garantias');
        $policy = app(WarrantyPolicy::class);
        $record = new Warranty;

        $this->assertTrue($policy->delete($this->userWithPermission, $record));
        $this->assertFalse($policy->delete($this->userWithoutPermission, $record));
    }

    public function test_warranty_policy_custom_methods(): void
    {
        $this->createPermission('exportar garantias');
        $this->createPermission('descargar certificado');
        $policy = app(WarrantyPolicy::class);
        $record = new Warranty;

        $this->assertTrue($policy->exportExcel($this->userWithPermission));
        $this->assertTrue($policy->downloadCertificate($this->userWithPermission, $record));
        $this->assertFalse($policy->exportExcel($this->userWithoutPermission));
        $this->assertFalse($policy->downloadCertificate($this->userWithoutPermission, $record));
    }

    // ── CustomerPolicy ──────────────────────────────────────────────

    public function test_customer_policy_anomalies(): void
    {
        $this->createPermission('ver clientes');
        $this->createPermission('editar clientes');
        $policy = app(CustomerPolicy::class);
        $record = new Customer;

        $this->assertTrue($policy->viewAny($this->userWithPermission));
        $this->assertTrue($policy->create($this->userWithPermission)); // maps to 'ver clientes'
        $this->assertTrue($policy->update($this->userWithPermission, $record));
        $this->assertTrue($policy->delete($this->userWithPermission, $record)); // maps to 'editar clientes'
        $this->assertFalse($policy->create($this->userWithoutPermission));
        $this->assertFalse($policy->update($this->userWithoutPermission, $record));
        $this->assertFalse($policy->delete($this->userWithoutPermission, $record));
    }

    // ── LabelLogPolicy ──────────────────────────────────────────────

    public function test_label_log_policy(): void
    {
        $this->createPermission('ver bitacora');
        $policy = app(LabelLogPolicy::class);
        $record = new LabelLog;

        $this->assertTrue($policy->viewAny($this->userWithPermission));
        $this->assertTrue($policy->view($this->userWithPermission, $record));
        $this->assertFalse($policy->viewAny($this->userWithoutPermission));
        $this->assertFalse($policy->view($this->userWithoutPermission, $record));
    }

    public function test_label_log_policy_query_serials(): void
    {
        $this->createPermission('consultar seriales');
        $policy = app(LabelLogPolicy::class);

        $this->assertTrue($policy->querySerials($this->userWithPermission));
        $this->assertFalse($policy->querySerials($this->userWithoutPermission));
    }

    // ── AuthServiceProvider ─────────────────────────────────────────

    public function test_auth_service_provider_boot(): void
    {
        $provider = new AuthServiceProvider(app());
        $provider->boot();

        $this->assertTrue(true);
    }
}
