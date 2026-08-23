<?php

/*
 * Thông tin bên bán, in trên hợp đồng du lịch.
 *
 * Để ở config chứ không viết thẳng vào mẫu Blade: hợp đồng là văn bản pháp lý, và mã số thuế hay
 * số giấy phép lữ hành đổi thì phải đổi ở đúng một chỗ. Cũng để mỗi môi trường tự khai - bản chạy
 * thử không nên mang số giấy phép thật.
 *
 * Giấy phép kinh doanh lữ hành nội địa là thứ luật bắt buộc phải có và phải ghi trên hợp đồng,
 * nên nó nằm ở đây chứ không phải trường tùy chọn.
 */

return [
    'name' => env('COMPANY_NAME', 'CÔNG TY TNHH DU LỊCH VIVU BOOKING'),
    'address' => env('COMPANY_ADDRESS', '123 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh'),
    'phone' => env('COMPANY_PHONE', '1900 6868'),
    'email' => env('COMPANY_EMAIL', 'hopdong@vivubooking.vn'),
    'tax_code' => env('COMPANY_TAX_CODE', '0312345678'),
    'license_no' => env('COMPANY_LICENSE_NO', '79-123/2024/TCDL-GPLHNĐ'),
    'representative' => env('COMPANY_REPRESENTATIVE', 'Nguyễn Văn A'),
    'representative_title' => env('COMPANY_REPRESENTATIVE_TITLE', 'Giám đốc'),
    'bank_account' => env('COMPANY_BANK_ACCOUNT', '0123456789 — Ngân hàng TMCP Ngoại thương Việt Nam (Vietcombank)'),
];
