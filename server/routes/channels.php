<?php

use Illuminate\Support\Facades\Broadcast;

/**
 * Kênh riêng của từng người dùng.
 *
 * Laravel gửi thông báo dạng broadcast vào đúng kênh này, nên chỉ cần một luật: **bạn chỉ nghe
 * được kênh của chính bạn**. Thiếu nó thì ai cũng đọc được thông báo của người khác — mà thông
 * báo ở đây mang tên hướng dẫn viên, lý do họ từ chối, và tình trạng đoàn.
 */
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
