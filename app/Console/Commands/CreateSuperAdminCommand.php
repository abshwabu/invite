<?php

namespace App\Console\Commands;

use App\Models\SuperAdmin;
use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'superadmin:create
                            {--name= : Name of the super admin}
                            {--email= : Unique email address}
                            {--password= : Password for the super admin}
                            {--role=owner : Role of the super admin (owner or support)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new super admin user for the Invite management panel';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Super Admin Name');
        $email = $this->option('email') ?: $this->ask('Super Admin Email');
        $password = $this->option('password') ?: $this->secret('Super Admin Password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:super_admins,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $role = in_array($this->option('role'), ['owner', 'support'], true) ? $this->option('role') : 'owner';

        $superAdmin = SuperAdmin::create([
            'name' => $name,
            'email' => strtolower(trim($email)),
            'password' => Hash::make($password),
            'role' => $role,
        ]);

        AuditLogService::log(
            'superadmin_created_via_cli',
            null,
            $superAdmin,
            ['email' => $superAdmin->email, 'name' => $superAdmin->name]
        );

        $path = config('invite.superadmin_path', 'sa-x7k2p9q4m1');

        $this->info("Super Admin [{$superAdmin->email}] created successfully!");
        $this->comment("Login at: /{$path}/login to complete mandatory 2FA setup.");

        return self::SUCCESS;
    }
}
