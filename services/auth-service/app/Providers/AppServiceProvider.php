<?php

namespace App\Providers;

use App\Components\Sanctum\PersonalAccessToken;
use Illuminate\Support\ServiceProvider;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\UserRepository;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    // Use the custom personal access token model for Sanctum
    Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
  }
}
