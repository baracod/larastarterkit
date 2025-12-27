# Changelog

All notable changes to `larastarterkit` will be documented in this file.

##  v0.0.1 - Initial Alpha Release - 2025-12-27

### 🎉 Initial Release of Larastarterkit

First public alpha release of **Larastarterkit**.
This package provides a powerful Modular Monolith starter kit for Laravel 11/12, fully integrated with Vue 3, Vuetify, and Nwidart Modules.

#### ✨ Key Features available in v0.0.1

* **Modular Architecture**: Built on top of `nwidart/laravel-modules`.
* **Fullstack Generators**:
    * `php artisan module:make <Name>`: Generates Backend (Laravel) and Frontend (Vue/Vuetify) structure.
    
* **Authentication**:
    * Pre-configured **Laravel Sanctum**.
    * **Role-Based Access Control (RBAC)** (Roles & Permissions system).
    
* **Stubs System**: Customizable stubs for Controllers, Models, and Vue components.
* **API Documentation**: Integrated **Swagger UI** setup.
* **Translation**: Auto-translation tools using Google API.

#### 📋 Requirements

* PHP >= 8.3
* Laravel 11.x / 12.x
* nwidart/laravel-modules

#### 📦 Installation

```bash
composer require baracod/larastarterkit

```