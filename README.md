# GreenPoint

ระบบส่งเสริมกิจกรรมปลูกต้นไม้และอนุรักษ์สิ่งแวดล้อมด้วยระบบสะสมคะแนนและรางวัล พัฒนาด้วย Laravel 12

## ความสามารถหลัก

- ระบบสมาชิก ฟีดชุมชน โปรไฟล์ Like และ Comment
- ส่งหลักฐานกิจกรรมและรอผู้ดูแลอนุมัติคะแนน
- คะแนนสะสม Level, Badge, Challenge และ Leaderboard
- ร้านรางวัลและประวัติการแลก
- ศูนย์ผู้ดูแลสำหรับสมาชิก กิจกรรม รางวัล Challenge, Badge, รายงาน และการตั้งค่า

## เริ่มต้นใช้งาน

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

เปิด `http://127.0.0.1:8000`

บัญชีตัวอย่างหลัง seed:

- สมาชิก: `kan@greenpoint.test` / `password`
- ผู้ดูแล: `admin@greenpoint.test` / `password`

## ทดสอบ

```bash
php artisan test
```
