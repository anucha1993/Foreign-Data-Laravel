<?php

// เฉพาะข้อความที่ใช้ในฟอร์มของระบบนี้ (ที่เหลือใช้ภาษาอังกฤษของ framework)
return [
    'required' => 'กรุณากรอก:attribute',
    'required_if' => 'กรุณากรอก:attribute',
    'required_without' => 'กรุณากรอก:attribute หรือแนบรูปภาพ',
    'string' => ':attribute ไม่ถูกต้อง',
    'email' => 'รูปแบบ:attribute ไม่ถูกต้อง',
    'in' => 'กรุณาเลือก:attribute',
    'array' => ':attribute ไม่ถูกต้อง',
    'file' => ':attribute ต้องเป็นไฟล์',
    'mimes' => ':attribute ต้องเป็นไฟล์รูปภาพ (jpg, png, webp, heic)',
    'max' => [
        'string' => ':attribute ยาวเกินไป',
        'file' => ':attribute ต้องมีขนาดไม่เกิน 5MB ต่อรูป',
        'array' => 'แนบ:attribute ได้ไม่เกิน :max รูป',
    ],
    'date_format' => 'รูปแบบ:attribute ไม่ถูกต้อง',
    'before' => ':attribute ไม่ถูกต้อง',
];
