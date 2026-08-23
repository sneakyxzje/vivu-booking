<?php

namespace App\Support;

/**
 * Đọc số tiền thành chữ tiếng Việt.
 *
 * Hợp đồng và hóa đơn ở Việt Nam bắt buộc ghi số tiền bằng chữ bên cạnh chữ số. Lý do có thật:
 * chữ số sửa được bằng một nét bút, cả dòng chữ thì không.
 *
 * Xử lý đúng bốn chỗ tiếng Việt hay sai:
 *
 *   - "mươi" và "mười": 21 là "hai mươi mốt", 11 là "mười một".
 *   - "mốt" thay cho "một" ở hàng đơn vị sau "mươi": 21 là "mốt", 11 là "một".
 *   - "lăm" thay cho "năm" ở hàng đơn vị khi có hàng chục: 15 là "mười lăm", 5 là "năm".
 *   - Chèn "lẻ" khi hàng chục trống mà vẫn còn hàng đơn vị: 105 là "một trăm lẻ năm".
 */
final class SoTienBangChu
{
    private const CHU_SO = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];

    /** Tên các nhóm ba chữ số, từ phải sang. Tỷ tỷ là đủ xa cho một hợp đồng tour. */
    private const NHOM = ['', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ', 'triệu tỷ'];

    public static function doc(float|int $soTien, string $donVi = 'đồng'): string
    {
        $so = (int) round($soTien);

        if ($so === 0) {
            return self::hoaChuDau('không ' . $donVi);
        }

        $am = $so < 0;
        $so = abs($so);

        // Cắt thành các nhóm ba chữ số, nhóm nhỏ nhất đứng đầu mảng.
        $nhom = [];
        while ($so > 0) {
            $nhom[] = $so % 1000;
            $so = intdiv($so, 1000);
        }

        $phan = [];
        foreach (array_reverse(array_keys($nhom)) as $i) {
            if ($nhom[$i] === 0) {
                continue;
            }

            // Nhóm không phải nhóm cao nhất thì luôn đọc đủ ba chữ số: 1.005 là "một nghìn không
            // trăm lẻ năm", không phải "một nghìn lẻ năm".
            $daySo = self::docBaChuSo($nhom[$i], $i !== count($nhom) - 1);
            $phan[] = trim($daySo . ' ' . self::NHOM[$i]);
        }

        $chu = trim(implode(' ', $phan)) . ' ' . $donVi;

        return self::hoaChuDau(($am ? 'âm ' : '') . $chu);
    }

    /** @param bool $doDayDu Đọc cả "không trăm" khi số nhỏ hơn 100. */
    private static function docBaChuSo(int $so, bool $doDayDu): string
    {
        $tram = intdiv($so, 100);
        $chuc = intdiv($so % 100, 10);
        $donVi = $so % 10;

        $ra = [];

        if ($tram > 0 || $doDayDu) {
            $ra[] = self::CHU_SO[$tram] . ' trăm';
        }

        if ($chuc === 0) {
            // Còn hàng đơn vị mà hàng chục trống thì phải có "lẻ", nhưng chỉ khi đã đọc hàng trăm.
            if ($donVi > 0 && ($tram > 0 || $doDayDu)) {
                $ra[] = 'lẻ ' . self::CHU_SO[$donVi];
            } elseif ($donVi > 0) {
                $ra[] = self::CHU_SO[$donVi];
            }

            return implode(' ', $ra);
        }

        $ra[] = $chuc === 1 ? 'mười' : self::CHU_SO[$chuc] . ' mươi';

        if ($donVi === 1 && $chuc > 1) {
            $ra[] = 'mốt';
        } elseif ($donVi === 5) {
            $ra[] = 'lăm';
        } elseif ($donVi > 0) {
            $ra[] = self::CHU_SO[$donVi];
        }

        return implode(' ', $ra);
    }

    private static function hoaChuDau(string $chu): string
    {
        return mb_strtoupper(mb_substr($chu, 0, 1)) . mb_substr($chu, 1);
    }
}
