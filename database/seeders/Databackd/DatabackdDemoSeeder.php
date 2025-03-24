<?php

namespace Database\Seeders\Databackd;

use App\Models\ClientDetails;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Designation;
use App\Models\EmployeeDetails;
use App\Models\Permission;
use App\Models\PermissionType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\SuperAdmin\Package;
use App\Models\Tax;
use App\Models\Team;
use App\Models\UniversalSearch;
use App\Models\UnitType;
use App\Models\User;
use App\Models\UserAuth;
use App\Scopes\ActiveScope;
use App\Scopes\CompanyScope;
use App\Services\Databackd\ChartOfAccounts;
use Database\Seeders\SuperAdminUsersTableSeeder;
use Illuminate\Database\Seeder;

/**
 * AFV base demo data: the company, its people, departments, clients and products.
 * Idempotent - everything is looked up (by name / email / EIK) before it is created.
 * Other demo seeders (database/seeders/Databackd/Demo/*) receive the company and build on this data.
 */
class DatabackdDemoSeeder extends Seeder
{

    /** Default (local-only) password of all demo accounts; override with `databackd:demo --password=`. */
    const PASSWORD = 'DemoFin#2026';

    const COMPANY_NAME = 'Демо Финанс Груп';

    const SUPERADMIN_EMAIL = 'superadmin@databackd.local';
    const ADMIN_EMAIL = 'admin@demofinans.bg';

    public ?Company $company = null;

    public const DEPARTMENTS = ['Финансов анализ', 'Счетоводство', 'Контролинг', 'Бизнес анализ', 'Продажби', 'Администрация'];

    public const DESIGNATIONS = ['Ръководител екип', 'Старши финансов анализатор', 'Финансов анализатор', 'Счетоводител', 'Финансов контрольор', 'Бизнес анализатор', 'Младши специалист', 'Управител'];

    /** name, email-local, department, designation, hourly rate, gender */
    public const EMPLOYEES = [
        ['Георги Стоянов Петров', 'g.petrov', 'Финансов анализ', 'Старши финансов анализатор', 38, 'male'],
        ['Мария Иванова Димитрова', 'm.dimitrova', 'Финансов анализ', 'Финансов анализатор', 30, 'female'],
        ['Николай Тодоров Георгиев', 'n.georgiev', 'Счетоводство', 'Счетоводител', 26, 'male'],
        ['Десислава Колева Атанасова', 'd.atanasova', 'Счетоводство', 'Счетоводител', 24, 'female'],
        ['Борислав Христов Маринов', 'b.marinov', 'Контролинг', 'Финансов контрольор', 34, 'male'],
        ['Елица Василева Янкова', 'e.yankova', 'Бизнес анализ', 'Бизнес анализатор', 32, 'female'],
        ['Стефан Костов Попов', 's.popov', 'Продажби', 'Ръководител екип', 36, 'male'],
        ['Виктория Панайотова Нинова', 'v.ninova', 'Администрация', 'Младши специалист', 18, 'female'],
    ];

    /** company name, EIK, city, contact person, gender, email-local, address */
    public const CLIENTS = [
        ['Балкан Трейд ЕООД', '200000001', 'София', 'Петър Александров Йорданов', 'male', 'p.yordanov', 'ул. Цар Борис III 112'],
        ['Родопи Фуудс АД', '200000002', 'Пловдив', 'Ивелина Георгиева Стойчева', 'female', 'i.stoycheva', 'бул. Марица 24'],
        ['Черно Море Логистик ООД', '200000003', 'Варна', 'Калоян Димов Рашков', 'male', 'k.rashkov', 'бул. Владислав Варненчик 187'],
        ['Старопланински Мебели ЕООД', '200000004', 'Габрово', 'Теодора Николова Банева', 'female', 't.baneva', 'ул. Радецка 9'],
        ['Дунав Агро ЕООД', '200000005', 'Русе', 'Живко Петков Радев', 'male', 'z.radev', 'ул. Александровска 55'],
        ['Витоша Софтуер ООД', '200000006', 'София', 'Радостина Иванова Цветкова', 'female', 'r.tsvetkova', 'бул. Цариградско шосе 115'],
        ['Тракия Инженеринг АД', '200000007', 'Стара Загора', 'Любомир Стефанов Славов', 'male', 'l.slavov', 'ул. Цар Симеон Велики 40'],
        ['Приморско Строй ЕООД', '200000008', 'Бургас', 'Анелия Христова Караиванова', 'female', 'a.karaivanova', 'ул. Александровска 78'],
        ['Златен Клас Пекарни ООД', '200000009', 'Велико Търново', 'Венцислав Йорданов Минев', 'male', 'v.minev', 'ул. Васил Левски 21'],
        ['Свети Георги Медика ЕООД', '200000010', 'Пловдив', 'д-р Силвия Борисова Нанева', 'female', 's.naneva', 'бул. България 63'],
        ['Пирин Турист АД', '200000011', 'Банско', 'Цветан Асенов Гочев', 'male', 'ts.gochev', 'ул. Пирин 14'],
        ['Македония Фарма ЕООД', '200000012', 'Благоевград', 'Надежда Петрова Лазарова', 'female', 'n.lazarova', 'ул. Тодор Александров 30'],
        ['Нова Енергия България ООД', '200000013', 'София', 'Огнян Милчев Данчев', 'male', 'o.danchev', 'бул. Джеймс Баучер 76'],
        ['Общинско Предприятие Чистота ЕООД', '200000014', 'Плевен', 'Мирела Стоилова Кънчева', 'female', 'm.kancheva', 'ул. Васил Левски 190'],
        ['Дигитал Хъб Консулт ЕООД', '200000015', 'София', 'Явор Красимиров Бонев', 'male', 'y.bonev', 'ул. Лъчезар Станчев 5'],
    ];

    /** name, price (EUR, without VAT), category, unit */
    public const PRODUCTS = [
        ['Месечно счетоводно обслужване - микро фирма', 120, 'Счетоводни услуги', 'Месец'],
        ['Месечно счетоводно обслужване - малка фирма', 250, 'Счетоводни услуги', 'Месец'],
        ['Месечно счетоводно обслужване - средна фирма', 480, 'Счетоводни услуги', 'Месец'],
        ['Изготвяне на годишен финансов отчет', 350, 'Счетоводни услуги', 'Бр.'],
        ['Годишна данъчна декларация', 180, 'Счетоводни услуги', 'Бр.'],
        ['Заплати и осигуровки - обработка на служител', 12, 'Счетоводни услуги', 'Бр.'],
        ['Месечен финансов анализ и отчет', 300, 'Финансов анализ', 'Месец'],
        ['Анализ на финансови коефициенти', 220, 'Финансов анализ', 'Бр.'],
        ['Анализ на паричните потоци', 260, 'Финансов анализ', 'Бр.'],
        ['Бюджетиране и прогноза за 12 месеца', 650, 'Финансов анализ', 'Бр.'],
        ['Консолидирана отчетност на група дружества', 900, 'Финансов анализ', 'Месец'],
        ['Консултация - финансов анализатор', 55, 'Консултации', 'Час'],
        ['Консултация - старши финансов анализатор', 75, 'Консултации', 'Час'],
        ['Консултация по данъчно планиране', 90, 'Консултации', 'Час'],
        ['Оценка на инвестиционен проект', 800, 'Консултации', 'Бр.'],
        ['Лиценз Anagami Finance View - Старт (на потребител)', 29, 'Софтуерни лицензи', 'Месец'],
        ['Лиценз Anagami Finance View - Бизнес (на потребител)', 59, 'Софтуерни лицензи', 'Месец'],
        ['Внедряване и настройка на платформата', 450, 'Софтуерни лицензи', 'Бр.'],
        ['Обучение - основи на финансовия анализ', 190, 'Обучения', 'Бр.'],
        ['Обучение - бюджетиране за мениджъри', 240, 'Обучения', 'Бр.'],
    ];

    public function run()
    {
        $this->company = $this->company();
        $companyId = $this->company->id;

        $this->superAdmin();
        ChartOfAccounts::ensureFor($companyId);
        $teams = $this->departments($companyId);
        $designations = $this->designations($companyId);
        $this->admin($companyId);
        $this->employees($companyId, $teams, $designations);
        $this->clients($companyId);
        $this->products($companyId);
    }

    protected function company(): Company
    {
        // The demo company is identified by its name; its id is whatever the database assigns
        $company = Company::where('company_name', self::COMPANY_NAME)->first();

        if ($company) {
            $this->localize($company);
            $this->onlyEuro($company);
            Company::where('id', $company->id)->update(['package_id' => $this->package()->id]);
            cache()->flush();

            return $company->refresh();
        }

        $company = new Company();
        $company->company_name = self::COMPANY_NAME;
        $company->app_name = self::COMPANY_NAME;
        $company->company_email = 'office@demofinans.bg';
        $company->company_phone = '+359 2 944 1020';
        $company->website = 'https://demofinans.bg';
        $company->address = 'бул. България 81, ет. 4, 1404 София';
        $company->timezone = 'Europe/Sofia';
        $company->locale = 'bg';
        $company->date_format = 'd.m.Y';
        $company->time_format = 'H:i';
        $company->status = 'active';
        $company->save();

        $this->localize($company);

        $eur = Currency::withoutGlobalScope(CompanyScope::class)->where('company_id', $company->id)->where('currency_code', 'EUR')->first();
        $company->currency_id = $eur->id;
        $company->saveQuietly();
        $this->onlyEuro($company);

        $company->package_id = $this->package()->id;
        $company->package_type = 'annual';
        $company->licence_expire_on = now()->addYears(5)->toDateString();
        $company->saveQuietly();

        return $company->refresh();
    }

    /** CompanyObserver copies locale / timezone / date format from the global settings - override them. */
    protected function localize(Company $company): void
    {
        Company::where('id', $company->id)->update([
            'locale' => 'bg', 'timezone' => 'Europe/Sofia', 'date_format' => 'd.m.Y', 'time_format' => 'H:i',
            'date_picker_format' => 'dd.mm.yyyy', 'moment_format' => 'DD.MM.YYYY',
        ]);

        // invoices / PDFs in Bulgarian
        \Illuminate\Support\Facades\DB::table('invoice_settings')->where('company_id', $company->id)->update(['locale' => 'bg']);
    }

    /**
     * Package for the demo company: a copy of "Medium" with explicit high limits
     * (the CUS_000329 limit check treats NULL limits as 0).
     */
    protected function package(): Package
    {
        $package = Package::where('name', 'Демо')->first();

        if (!$package) {
            $package = Package::where('name', 'Medium')->first()->replicate();
            $package->name = 'Демо';
            $package->default = 'no';
            $package->is_private = 1;
        }

        $package->max_employees = 1000;
        $package->max_clients = 10000;
        $package->max_invoices = 1000000;
        $package->save();

        return $package;
    }

    /** The demo company works in EUR only: drop the other currencies copied in by CompanyObserver. */
    protected function onlyEuro(Company $company): void
    {
        $eur = Currency::withoutGlobalScope(CompanyScope::class)->where('company_id', $company->id)->where('currency_code', 'EUR')->first();

        if ($company->currency_id != $eur->id) {
            Company::where('id', $company->id)->update(['currency_id' => $eur->id]);
        }

        Currency::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $company->id)->where('currency_code', '!=', 'EUR')->delete();
    }

    protected function password(): string
    {
        return (string)config('databackd.demo_password', self::PASSWORD);
    }

    protected function superAdmin(): void
    {
        // A demo super admin only makes sense on a local database - never create it on a real server
        if (!str_ends_with((string)config('database.connections.' . config('database.default') . '.database'), '_local')) {
            return;
        }

        if (UserAuth::where('email', self::SUPERADMIN_EMAIL)->exists()) {
            return;
        }

        $user = new User();
        $user->name = 'Системен Администратор';
        $user->email = self::SUPERADMIN_EMAIL;
        $user->is_superadmin = true;
        $user->locale = 'bg';
        $user->country_id = 33;
        $user->save();

        $auth = UserAuth::create(['email' => $user->email, 'password' => bcrypt($this->password()), 'email_verified_at' => now()]);
        $user->user_auth_id = $auth->id;
        $user->saveQuietly();

        SuperAdminUsersTableSeeder::superadminRolePermissionAttach($user);
    }

    protected function departments(int $companyId): array
    {
        $teams = [];

        foreach (self::DEPARTMENTS as $name) {
            $team = Team::withoutGlobalScope(CompanyScope::class)->where('company_id', $companyId)->where('team_name', $name)->first();

            if (!$team) {
                $team = new Team();
                $team->company_id = $companyId;
                $team->team_name = $name;
                $team->save();
            }

            $teams[$name] = $team->id;
        }

        return $teams;
    }

    protected function designations(int $companyId): array
    {
        $result = [];

        foreach (self::DESIGNATIONS as $name) {
            $result[$name] = Designation::withoutGlobalScope(CompanyScope::class)
                ->firstOrCreate(['company_id' => $companyId, 'name' => $name])->id;
        }

        return $result;
    }

    /** Creates (or returns) a user with its auth record and role. */
    protected function user(int $companyId, string $roleName, string $name, string $email, string $gender, array $extra = []): User
    {
        $user = User::withoutGlobalScopes([CompanyScope::class, ActiveScope::class])
            ->where('company_id', $companyId)->where('email', $email)->first();

        if ($user) {
            return $user;
        }

        $user = new User();
        $user->company_id = $companyId;
        $user->name = $name;
        $user->email = $email;
        $user->gender = $gender;
        $user->status = 'active';
        $user->locale = 'bg';
        $user->country_id = 33;
        $user->country_phonecode = 359;
        $user->mobile = '8' . str_pad((string)mt_rand(70000000, 99999999), 8, '0');
        foreach ($extra as $k => $v) {
            $user->$k = $v;
        }
        $user->save();

        $auth = UserAuth::where('email', $email)->first()
            ?? UserAuth::create(['email' => $email, 'password' => bcrypt($this->password()), 'email_verified_at' => now()]);
        $user->user_auth_id = $auth->id;
        $user->saveQuietly();

        $role = Role::withoutGlobalScope(CompanyScope::class)->where('name', $roleName)->where('company_id', $companyId)->first();
        $user->roles()->attach($role->id);

        return $user;
    }

    protected function employeeDetails(User $user, int $companyId, array $attrs): void
    {
        $existing = EmployeeDetails::withoutGlobalScope(CompanyScope::class)->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->department_id = $attrs['department_id'] ?? $existing->department_id;
            $existing->designation_id = $attrs['designation_id'] ?? $existing->designation_id;
            $existing->saveQuietly();

            return;
        }

        $details = new EmployeeDetails();
        $details->user_id = $user->id;
        $details->company_id = $companyId;
        $details->employee_id = 'EMP-' . str_pad((string)$user->id, 3, '0', STR_PAD_LEFT);
        foreach ($attrs as $k => $v) {
            $details->$k = $v;
        }
        $details->save();

        $search = new UniversalSearch();
        $search->searchable_id = $user->id;
        $search->company_id = $companyId;
        $search->title = $user->name;
        $search->route_name = 'employees.show';
        $search->module_type = 'employee';
        $search->save();

        $employeeRole = Role::withoutGlobalScope(CompanyScope::class)->where('name', 'employee')->where('company_id', $companyId)->first();

        if (!$user->roles()->where('roles.id', $employeeRole->id)->exists()) {
            $user->roles()->attach($employeeRole->id);
        }
    }

    protected function admin(int $companyId): void
    {
        $fresh = !User::withoutGlobalScopes([CompanyScope::class, ActiveScope::class])->where('company_id', $companyId)->where('email', self::ADMIN_EMAIL)->exists();
        $admin = $this->user($companyId, 'admin', 'Александър Георгиев Тодоров', self::ADMIN_EMAIL, 'male');

        $this->employeeDetails($admin, $companyId, [
            'address' => 'бул. България 81, София',
            'hourly_rate' => 60,
            'joining_date' => '2024-01-08',
            'about_me' => 'Управител и водещ финансов консултант.',
            'department_id' => Team::withoutGlobalScope(CompanyScope::class)->where('company_id', $companyId)->where('team_name', 'Администрация')->value('id'),
            'designation_id' => Designation::withoutGlobalScope(CompanyScope::class)->where('company_id', $companyId)->where('name', 'Управител')->value('id'),
        ]);

        if ($fresh) {
            $type = PermissionType::where('name', 'all')->first();

            foreach (Permission::orderBy('id')->pluck('id') as $permissionId) {
                $admin->permissionTypes()->attach([$permissionId => ['permission_type_id' => $type->id ?? PermissionType::ALL]]);
            }
        }
    }

    protected function employees(int $companyId, array $teams, array $designations): void
    {
        foreach (self::EMPLOYEES as $i => [$name, $local, $dept, $designation, $rate, $gender]) {
            $user = $this->user($companyId, 'employee', $name, $local . '@demofinans.bg', $gender);

            $this->employeeDetails($user, $companyId, [
                'address' => 'София',
                'hourly_rate' => $rate,
                'joining_date' => date('Y-m-d', strtotime('2024-02-01 +' . ($i * 37) . ' days')),
                'department_id' => $teams[$dept],
                'designation_id' => $designations[$designation],
                'about_me' => $designation . ' в екипа на Демо Финанс Груп.',
            ]);
        }
    }

    protected function clients(int $companyId): void
    {
        $role = Role::withoutGlobalScope(CompanyScope::class)->where('name', 'client')->where('company_id', $companyId)->first();

        foreach (self::CLIENTS as [$company, $eik, $city, $contact, $gender, $local, $address]) {
            $already = ClientDetails::withoutGlobalScope(CompanyScope::class)->where('company_id', $companyId)->where('gst_number', $eik)->exists();

            if ($already) {
                continue;
            }

            $user = $this->user($companyId, 'client', $contact, $local . '@' . $this->domain($company), $gender);

            $details = new ClientDetails();
            $details->user_id = $user->id;
            $details->company_id = $companyId;
            $details->company_name = $company;
            $details->address = $address . ', ' . $city;
            $details->city = $city;
            $details->state = $city;
            $details->country_id = 33;
            $details->postal_code = (string)mt_rand(1000, 9999);
            $details->website = 'https://' . $this->domain($company);
            $details->tax_name = 'ЕИК';
            $details->gst_number = $eik;
            $details->office_phone = '+359 2 ' . mt_rand(900, 999) . ' ' . mt_rand(1000, 9999);
            $details->save();

            $search = new UniversalSearch();
            $search->searchable_id = $user->id;
            $search->company_id = $companyId;
            $search->title = $company;
            $search->route_name = 'clients.show';
            $search->module_type = 'client';
            $search->save();
        }
    }

    protected function domain(string $companyName): string
    {
        $slug = strtolower(strtr(trim(explode(' ', $companyName)[0]), [
            'А' => 'a', 'Б' => 'b', 'В' => 'v', 'Г' => 'g', 'Д' => 'd', 'Е' => 'e', 'Ж' => 'zh', 'З' => 'z', 'И' => 'i', 'Й' => 'y', 'К' => 'k', 'Л' => 'l', 'М' => 'm', 'Н' => 'n', 'О' => 'o', 'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't', 'У' => 'u', 'Ф' => 'f', 'Х' => 'h', 'Ц' => 'ts', 'Ч' => 'ch', 'Ш' => 'sh', 'Щ' => 'sht', 'Ъ' => 'a', 'Ю' => 'yu', 'Я' => 'ya',
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sht', 'ъ' => 'a', 'ю' => 'yu', 'я' => 'ya', 'ь' => '',
        ]));

        return preg_replace('/[^a-z0-9]+/', '', $slug) . '.bg';
    }

    protected function products(int $companyId): void
    {
        $vat = Tax::withoutGlobalScope(CompanyScope::class)->firstOrCreate(
            ['company_id' => $companyId, 'tax_name' => 'ДДС'],
            ['rate_percent' => 20]
        );

        $units = [];

        foreach (['Месец', 'Бр.', 'Час'] as $unit) {
            $units[$unit] = UnitType::withoutGlobalScope(CompanyScope::class)
                ->firstOrCreate(['company_id' => $companyId, 'unit_type' => $unit])->id;
        }

        $categories = [];

        foreach (array_unique(array_column(self::PRODUCTS, 2)) as $category) {
            $categories[$category] = ProductCategory::withoutGlobalScope(CompanyScope::class)
                ->firstOrCreate(['company_id' => $companyId, 'category_name' => $category])->id;
        }

        $adminId = User::withoutGlobalScopes([CompanyScope::class, ActiveScope::class])->where('company_id', $companyId)->where('email', self::ADMIN_EMAIL)->value('id');

        foreach (self::PRODUCTS as $i => [$name, $price, $category, $unit]) {
            if (Product::withoutGlobalScope(CompanyScope::class)->where('company_id', $companyId)->where('name', $name)->exists()) {
                continue;
            }

            $product = new Product();
            $product->company_id = $companyId;
            $product->name = $name;
            $product->price = $price;
            $product->taxes = json_encode([$vat->id]);
            $product->allow_purchase = 1;
            $product->description = '<p>' . $name . '</p>';
            $product->category_id = $categories[$category];
            $product->unit_id = $units[$unit];
            $product->sku = 'DFG-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);
            $product->added_by = $adminId;
            $product->save();
        }
    }

}
