<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
  /**
   * Seed the application's database.
   */
  public function run(): void
  {
    $adminLastname = Env("ADMIN_LASTNAME");
    $adminFirstname = Env("ADMIN_FIRSTNAME");
    $adminUsername = Env("ADMIN_USERNAME");
    $adminEmail = Env("ADMIN_EMAIL");
    $adminPassword = Env("ADMIN_PASSWORD");

    if ($adminLastname && $adminFirstname && $adminUsername && $adminEmail && $adminPassword) {
      // Debugging output
      $adminDetails = [
        "Lastname" => $adminLastname,
        "Firstname" => $adminFirstname,
        "Username" => $adminUsername,
        "Email" => $adminEmail,
        "Password" => $adminPassword,
      ];

      $this->dumpSuperAdminFromConfig();

      $data = [
        "lastname" => $adminLastname,
        "firstname" => $adminFirstname,
        "email" => $adminEmail,
        "password" => Hash::make($adminPassword),
      ];

      $this->createUser($data);
    } else {
      echo "Admin user not created. Please ensure all required environment variables are set.\n";
      echo "Run `php artisan config:clear` to refresh the configuration cache and try again.\n";
    }

    $this->createUser([
      "lastname" => "Admin",
      "firstname" => "Demo",
      "email" => "admin@flippad.com",
      "password" => Hash::make("@Admin123"),
    ]);

    $this->createUser([
      "lastname" => "User",
      "firstname" => "Demo",
      "email" => "user@flippad.com",
      "password" => Hash::make("@User123"),
    ]);
  }

  /**
   * Dump the super admin details from the configuration.
   *
   * @return void
   */
  private function dumpSuperAdminFromConfig(): void
  {
    $adminDetails = [
      "Lastname" => Env("ADMIN_LASTNAME"),
      "Firstname" => Env("ADMIN_FIRSTNAME"),
      "Username" => Env("ADMIN_USERNAME"),
      "Email" => Env("ADMIN_EMAIL"),
      "Password" => Env("ADMIN_PASSWORD"),
    ];

    echo "\nAdmin user created with the following details:\n\n";
    foreach ($adminDetails as $key => $value) {
      echo "  - $key: $value\n";
    }
  }

  /**
   * Create a user.
   *
   * @param array|null $data
   * @return User
   */
  private function createUser(?array $data): User
  {
    $userData = $data ?? [];
    
    // Use firstOrCreate to avoid duplicate entries
    if (isset($userData['email'])) {
      $user = User::firstOrCreate(
        ['email' => $userData['email']],
        $userData
      );
    } else {
      $user = User::factory()->create($userData);
    }

    return $user;
  }
}
