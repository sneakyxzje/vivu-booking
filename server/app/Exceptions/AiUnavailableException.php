<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Không gọi được model, hoặc gọi được nhưng nhận về thứ không đọc nổi.
 *
 * Thông điệp của ngoại lệ này đi thẳng ra cho khách đọc nên phải là tiếng Việt và
 * không chứa chi tiết kỹ thuật; chi tiết thật nằm ở storage/logs.
 */
class AiUnavailableException extends RuntimeException
{
}
