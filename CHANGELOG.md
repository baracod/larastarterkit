# Changelog

All notable changes to `larastarterkit` will be documented in this file.

## v0.0.3 Fix: Auth module installation logic - 2025-12-27

### 🐛 Bug Fix

This release fixes a critical issue in the `larastarterkit:install` command where the **Auth module** was not being generated correctly during the initial setup.

#### What's Fixed?

* **Auth Module**: The installer now correctly triggers the generation of the `Auth` module (Sanctum, Permissions, Login/Register logic) after the main scaffolding.

#### 🛠 How to Update

If you already installed v0.0.2 and are missing the Auth module, please update the package and re-run the installer:

```bash
composer update baracod/larastarterkit
php artisan larastarterkit:install

```
## v0.0.2 - Automated Installer & Fullstack Scaffolding - 2025-12-27

### 🚀 v0.0.2: The "Zero-Config" Update

This release introduces a powerful new installation command that completely automates the setup of the Modular Monolith architecture and the Vue 3 + Vuetify frontend stack.

#### ✨ New Features

* **New Installer Command**: Introduced `php artisan larastarterkit:install`.
  
  * This single command handles the entire project setup from A to Z.
  
* **Frontend Scaffolding**:
  
  * Automatically copies the full **Vue 3 + Vuetify + TypeScript** architecture.
  * Installs `vite.config.ts`, `tsconfig.json`, `themeConfig.ts`, and more.
  * Updates `package.json` with required NPM dependencies.
  
* **Architecture Setup**:
  
  * Creates the `Modules/` directory structure.
  * Generates `modules.json` and `menuItems.ts`.
  
* **Dependency Management**:
  
  * Automatically configures `wikimedia/composer-merge-plugin` in `composer.json` to allow Modules to have their own dependencies.
  
* **Authentication**:
  
  * Automated setup and publication of **Laravel Sanctum**.
  
* **Routing**:
  
  * Injects the SPA "Catch-all" route in `routes/web.php`.
  

#### 🛠 Usage

To upgrade and install the stack on a fresh Laravel project:

```bash
# 1. Update the package
composer update baracod/larastarterkit

# 2. Run the new installer
php artisan larastarterkit:install

# 3. Finalize setup
npm install
npm run dev


```
## v0.0.1 - Initial Alpha Release - 2025-12-27

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