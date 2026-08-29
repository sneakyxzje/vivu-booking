import { useEffect } from "react";

/**
 * Đặt tiêu đề và các thẻ meta của trang hiện tại.
 *
 * Ứng dụng một trang chỉ có duy nhất một `index.html`, nên nếu không đụng gì tới `<head>` thì mọi
 * trang - trang chủ, trang tour Hạ Long, trang chính sách - đều mang cùng một tiêu đề. Đó là thứ
 * hiện trên tab trình duyệt, trong lịch sử, trong danh sách dấu trang, và trong ô xem trước khi ai
 * đó dán liên kết vào Zalo hay Facebook.
 *
 * **Giới hạn phải nói rõ:** thẻ đặt bằng JavaScript sau khi trang đã tải. Trình thu thập nào không
 * chạy JavaScript (phần lớn trình xem trước liên kết của mạng xã hội) vẫn chỉ đọc được thẻ tĩnh
 * trong `index.html`. Muốn đúng cho cả nhóm đó thì phải dựng sẵn HTML từ phía máy chủ - một việc
 * khác hẳn, không phải thứ hook này giả vờ làm được.
 */
export interface DocumentMeta {
  title: string;
  description?: string;
  /** Ảnh đại diện khi dán liên kết. Nên là URL tuyệt đối. */
  image?: string | null;
  /** Địa chỉ chuẩn của trang. Bỏ trống thì lấy địa chỉ hiện tại. */
  canonical?: string;
}

const TEN_TRANG = "Vivu Booking";

/** Tìm hoặc tạo một thẻ meta, rồi đặt nội dung cho nó. */
function datMeta(khoa: "name" | "property", ten: string, noiDung: string | null) {
  const selector = `meta[${khoa}="${ten}"]`;
  let the = document.head.querySelector<HTMLMetaElement>(selector);

  if (noiDung === null) {
    the?.remove();
    return;
  }

  if (!the) {
    the = document.createElement("meta");
    the.setAttribute(khoa, ten);
    document.head.appendChild(the);
  }

  the.setAttribute("content", noiDung);
}

function datCanonical(href: string) {
  let the = document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]');

  if (!the) {
    the = document.createElement("link");
    the.rel = "canonical";
    document.head.appendChild(the);
  }

  the.href = href;
}

export function useDocumentMeta({ title, description, image, canonical }: DocumentMeta) {
  useEffect(() => {
    const tieuDeDayDu = title === TEN_TRANG ? title : `${title} | ${TEN_TRANG}`;
    const diaChi = canonical ?? window.location.href.split("?")[0];

    document.title = tieuDeDayDu;
    datCanonical(diaChi);

    datMeta("property", "og:title", tieuDeDayDu);
    datMeta("property", "og:type", "website");
    datMeta("property", "og:url", diaChi);
    datMeta("property", "og:site_name", TEN_TRANG);
    datMeta("name", "twitter:card", image ? "summary_large_image" : "summary");

    if (description) {
      datMeta("name", "description", description);
      datMeta("property", "og:description", description);
    }

    // Ảnh là tùy chọn: trang chính sách không có ảnh nào đại diện cho nó. Xóa hẳn thẻ thay vì để
    // lại ảnh của trang trước đó, nếu không thì dán liên kết trang chính sách lại ra ảnh Hạ Long.
    datMeta("property", "og:image", image ?? null);
  }, [title, description, image, canonical]);
}

export default useDocumentMeta;
