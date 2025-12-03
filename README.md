# Route to Postman for Laravel
[![Latest Version](https://img.shields.io/packagist/v/dogukantokgoz/route-to-postman.svg?style=flat-square&label=Package%20Version)](https://packagist.org/packages/dogukantokgoz/route-to-postman)


Generate organized Postman collections directly from your Laravel routes with intelligent automation.

![Route to Postman](https://i.imgur.com/hewTR1I.png)


## Features

### 📂 Smart File Structure
The package automatically organizes your Postman collection based on your route prefixes.
- **Folders:** Created based on the route prefix (e.g., `api/users` -> `users` folder).
- **Requests:** Named using the controller method in PascalCase (e.g., `UserController@store` -> `Store`).
- **Filtering:** Routes defined in `routes/api.php` that do not have a valid controller method are automatically skipped to keep your collection clean.

### 📝 Intelligent Body Generation
The package analyzes your code to generate sample request bodies for `POST`, `PUT`, and `PATCH` requests.
1.  **FormRequest:** If a method uses a `FormRequest`, validation rules are used to generate the body.
2.  **Model Inference:** If no `FormRequest` is used, the package infers the Model from the Controller name (e.g., `ProductController` -> `Product` model). It then uses the model's `fillable` attributes to generate the request body.

### 🔐 Automated Authentication
Forget about manually copying and pasting tokens!
1.  **Login Detection:** The package automatically finds routes ending in `login` or containing `auth/login`.
2.  **Auto-Script Injection:** A Postman test script is injected into the login request to capture the token.
3.  **Smart Bearer Token Assignment:** Bearer token is automatically added to routes that have middleware assigned. Public routes (without middleware) and excluded routes will not receive bearer token authentication.

**Expected Login Response:**
The script expects the token to be in `data.token`. Example:
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "token": "your_generated_token_here"
    }
}
```
*Once you run the Login request in Postman, the token is automatically saved and used for all other requests.*

**Bearer Token Rules:**
- ✅ **Added to:** Routes with any middleware (except `api` middleware which is automatically added by Laravel)
- ❌ **Not added to:** 
  - Routes without middleware (public routes)
  - Routes matching patterns in `excluded_routes` config (login, register, password reset, email verification, etc.)

**Excluded Routes Configuration:**
You can configure which routes should not receive bearer token in `config/postman.php`:
```php
'excluded_routes' => [
    // Authentication routes
    'login',
    'register',
    'auth/google',
    'auth/facebook',
    
    // Password reset routes
    'password-reset',
    'password/reset',
    'password/email',
    '/password/reset',
    'forgot-password',
    'forgotpassword',
    'reset-password',
    'resetpassword',
    
    // Email verification routes
    'email/verify',
    'email/resend',
    '/email/verify',
    '/email/resend',
],
```

### ⚙️ Configuration
- **Collection Name:** The Postman collection name is taken from your `APP_NAME` environment variable (defaults to 'Laravel Routes').

## Installation

```bash
composer require dogukantokgoz/route-to-postman
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="dogukantokgoz\Route2Postman\PostmanServiceProvider" --tag=postman-config
```

## Usage

Run the command to generate your collection:

```bash
php artisan route:export
```

The collection will be saved to `storage/postman/route_collection.json`. Import this file into Postman.

## License

[MIT](./LICENSE)

