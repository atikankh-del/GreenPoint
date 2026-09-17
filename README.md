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

## รูปหลักฐานกิจกรรม

แนบ JPG/JPEG/PNG ได้ 1 รูป ไม่เกิน 5 MB และต้องแนบเมื่อขอคะแนน รูปเก็บใน
`storage/app/evidence` และเปิดผ่าน route `posts.image` ที่ตรวจสิทธิ์ทุกครั้งเท่านั้น
ห้ามเปิดโฟลเดอร์นี้ผ่าน web server หรือ symlink ลง `public`.
ตั้ง document root เป็น `public` และให้ PHP เขียน `storage` ได้.
ตั้ง `upload_max_filesize = 8M` และ `post_max_size = 12M` ใน php.ini แล้วเริ่ม PHP ใหม่
เพื่อให้ Laravel ตรวจขนาด 5 MB และแสดงข้อผิดพลาดภาษาไทยได้.
โพสต์เก่าที่ไม่มีรูปไม่จำเป็นต้องแก้ไขหรือ seed ใหม่.
