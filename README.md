Access the application at: [http://localhost:8000](http://localhost:8000)

---

## 🛠 Tech Stack

- **Backend:** Laravel 13
- **Frontend:** Vite, Blade / Livewire
- **Real-time:** Laravel Reverb (WebSockets)
- **Database:** MariaDB / MySQL
- **Email:** SMTP (Gmail Integration)

## 📄 License

TheHere is the professional English version of your `README.md`. You can copy and paste this directly into your file:

`````markdown
# Top Link-Vote

<p align="center"><a href="[https://laravel.com](https://laravel.com)" target="_blank"><img src="[https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg](https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg)" width="400" alt="Laravel Logo"></a></p>

A web application for link sharing and real-time voting, built with the Laravel framework.

---

## 🚀 Quick Setup Guide

Follow these steps to get the project running on your local machine:

### 1. Clone the Repository

````bash
git clone <repository-url>
cd Top-Link-Vote

### 1. Clone the Repository
```bash
composer install

### 3. Setup Environment File
Create your .env file from the template:

```bash
cp .env.example .env
Note: Open the .env file and update DB_DATABASE, DB_USERNAME, and DB_PASSWORD to match your local MySQL configuration.

### 4. Generate Application Key

```bash
php artisan key:generate

### 5. Run Database Migrations
Ensure you have created a database named top_link_vote in your MySQL server before running this command:

```bash
php artisan migrate

### 6. Link Storage
Create a symbolic link from public/storage to storage/app/public to handle file uploads:

```bash
php artisan storage:link

### 7. Compile Assets & Real-time Server
Install Node dependencies and start the development server:

```bash
npm install
npm run dev
If you are using real-time features (Reverb), run the following in a separate terminal:

```bash
php artisan reverb:start

### 8. Start the Application
```bash
php artisan serve
Access the application at: http://localhost:8000

🛠 Tech Stack
Backend: Laravel 11

Frontend: Vite, Blade / Livewire

Real-time: Laravel Reverb (WebSockets)

Database: MariaDB / MySQL

Email: SMTP (Gmail Integration)

📄 License
The Laravel framework is open-sourced software licensed under the MIT license.
````
`````

```

```
