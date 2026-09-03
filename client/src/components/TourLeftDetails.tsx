import React, { useEffect, useState } from "react";
import type { Tour, Service, TourItinerary, TourSchedule } from "@/types";
import policyService from "@/services/policyService";
import type { PolicyResponse } from "@/services/policyService";
import {
  MapPinIcon,
  ClockIcon,
  CompassIcon,
  HotelIcon,
  LandmarkIcon,
} from "@/components/Icons";
import { TourReviewsSection } from "@/components/TourReviewsSection";
import { TourDepartures } from "@/components/TourDepartures";

interface TourLeftDetailsProps {
  tour: Tour;
  /** Chuyến khách đang chọn ở thanh bên. Null khi tour chưa có chuyến nào còn bán. */
  selectedSchedule: TourSchedule | null;
  /**
   * Đổi chuyến đang chọn.
   *
   * Bảng lịch khởi hành nằm ở cột nội dung này, còn hộp đặt tour nằm ở thanh bên — hai chỗ phải
   * chọn cùng một chuyến, nếu không khách bấm ngày ở bảng rồi bấm Đặt ở hộp lại đi mất một ngày
   * khác. Nên trạng thái ở trang cha, cả hai cùng đọc và cùng ghi.
   */
  onScheduleChange: (schedule: TourSchedule) => void;
}

const getServiceIcon = (name: string) => {
  const lowercaseName = name.toLowerCase();
  if (
    lowercaseName.includes("xe") ||
    lowercaseName.includes("di chuyển") ||
    lowercaseName.includes("đưa đón")
  ) {
    return (
      <svg
        className="w-5 h-5 text-primary-600"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"
        />
      </svg>
    );
  }
  if (
    lowercaseName.includes("khách sạn") ||
    lowercaseName.includes("resort") ||
    lowercaseName.includes("nghỉ") ||
    lowercaseName.includes("homestay")
  ) {
    return <HotelIcon className="w-5 h-5 text-primary-600" />;
  }
  if (
    lowercaseName.includes("ăn") ||
    lowercaseName.includes("ẩm thực") ||
    lowercaseName.includes("uống") ||
    lowercaseName.includes("bữa")
  ) {
    return (
      <svg
        className="w-5 h-5 text-primary-600"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2.2}
          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
        />
      </svg>
    );
  }
  if (
    lowercaseName.includes("hướng dẫn") ||
    lowercaseName.includes("guide") ||
    lowercaseName.includes("nhân sự")
  ) {
    return (
      <svg
        className="w-5 h-5 text-primary-600"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
        />
      </svg>
    );
  }
  if (
    lowercaseName.includes("vé") ||
    lowercaseName.includes("tham quan") ||
    lowercaseName.includes("vé vào cổng")
  ) {
    return <LandmarkIcon className="w-5 h-5 text-primary-600" />;
  }
  return <CompassIcon className="w-5 h-5 text-primary-600" />;
};

export const TourLeftDetails: React.FC<TourLeftDetailsProps> = ({
  tour,
  selectedSchedule,
  onScheduleChange,
}) => {
  const [expandedDay, setExpandedDay] = useState<number | null>(1);
  const [expandedFaq, setExpandedFaq] = useState<number | null>(null);
  /** Ngày đang mở trong hộp thoại chi tiết. Null là đang đóng. */
  const [ngayDangMo, setNgayDangMo] = useState<TourItinerary | null>(null);

  /* Esc đóng hộp thoại. Gắn khi mở và gỡ khi đóng, để trang không giữ một trình nghe phím thừa. */
  useEffect(() => {
    if (!ngayDangMo) return;

    const dongKhiEsc = (su: KeyboardEvent) => {
      if (su.key === "Escape") setNgayDangMo(null);
    };

    window.addEventListener("keydown", dongKhiEsc);

    return () => window.removeEventListener("keydown", dongKhiEsc);
  }, [ngayDangMo]);

  /*
   * Chính sách hủy đọc từ máy chủ, không viết cứng trong giao diện.
   *
   * Bảng phí nằm trong cơ sở dữ liệu và điều hành sửa được. Chép nó thành chữ ở đây thì có hai
   * bản: bản khách đọc trước khi mua, và bản hệ thống tính lúc hủy. Hai bản giống nhau đúng tới
   * lần sửa đầu tiên — và ở dự án này chúng đã lệch từ lâu: trang tour hứa "hủy trước 15 ngày
   * miễn phí hoàn toàn" trong khi hệ thống giữ lại 10%, hứa "trong vòng 3 ngày mất 100%" trong
   * khi bậc 2–4 ngày vẫn hoàn 30%.
   *
   * `PolicyPage` đã đọc từ đây từ lâu; trang chi tiết tour — nơi khách thật sự đọc điều khoản
   * trước khi bấm đặt — thì chưa.
   */
  const [policy, setPolicy] = useState<PolicyResponse | null>(null);

  useEffect(() => {
    let huy = false;

    policyService
      .get()
      .then((data) => {
        if (!huy) setPolicy(data);
      })
      .catch(() => undefined);

    return () => {
      huy = true;
    };
  }, []);

  const bacHoan = policy?.cancellation.rules ?? [];

  const toggleDay = (dayNum: number) => {
    setExpandedDay(expandedDay === dayNum ? null : dayNum);
  };

  const toggleFaq = (idx: number) => {
    setExpandedFaq(expandedFaq === idx ? null : idx);
  };

  const faqs = [
    {
      q: "Giá tour hiển thị đã bao gồm những chi phí gì?",
      /*
       * Đọc từ dịch vụ của chính tour này, không kể một danh sách chung.
       *
       * Bản cũ liệt kê cứng "xe máy lạnh, resort cao cấp, bữa ăn đặc sản..." cho MỌI tour, kể cả
       * tour một ngày không có lưu trú. Danh sách thật đã nằm sẵn ở `tour.services` do quản trị
       * nhập, và nó là thứ duy nhất đúng với từng tour.
       */
      a: tour.services && tour.services.length > 0
        ? "Giá tour đã bao gồm: " +
          tour.services.map((dv: Service) => dv.name).join(", ") +
          ". Các chi phí cá nhân ngoài chương trình do Quý khách tự chi trả."
        : "Danh sách dịch vụ đi kèm của tour này đang được cập nhật. Vui lòng liên hệ tổng đài để được tư vấn chi tiết trước khi đặt.",
    },
    {
      q: "Quy định về việc hủy đặt tour và hoàn tiền như thế nào?",
      /*
       * Câu trả lời dựng từ bảng phí THẬT, không viết cứng.
       *
       * Bản cũ khẳng định "hủy trước 15 ngày là hoàn toàn miễn phí" trong khi hệ thống giữ lại
       * 10%, và "trong vòng 3 ngày mất 100%" trong khi bậc 2–4 ngày vẫn hoàn 30%. Đây là trang
       * khách đọc TRƯỚC khi trả tiền, nên mỗi con số sai ở đây là một khiếu nại sau khi hủy.
       */
      a: bacHoan.length > 0
        ? "Mức hoàn phụ thuộc thời điểm hủy: " +
          bacHoan.map((bac) => `${bac.window} hoàn ${bac.refund_percent}%`).join("; ") +
          ". Phí hủy tính trên giá trị đơn, tiền hoàn trừ trên số tiền đã thanh toán."
        : "Mức hoàn phụ thuộc thời điểm hủy, xem bảng chi tiết ở mục Chính sách hoàn hủy bên dưới.",
    },
    {
      q: "Có chính sách giảm giá riêng cho trẻ em không?",
      a: "Có, em bé dưới 2 tuổi được miễn phí dịch vụ (ngủ chung phòng và bố mẹ tự lo ăn uống cho bé). Trẻ em từ 2 - 12 tuổi được áp dụng chính sách giá trẻ em. Người lớn từ 12 tuổi trở lên tính theo giá người lớn.",
    },
    {
      q: "Tôi có thể tự thay đổi hoặc thêm bớt địa điểm tham quan không?",
      a: "Vì đây là tour trọn gói ghép đoàn theo lịch trình định sẵn nhằm đảm bảo tối ưu chi phí và thời gian di chuyển chung cho đoàn, lịch trình không thể thay đổi giữa chừng. Đối với nhóm đi đông từ 10 người trở lên, quý khách có thể liên hệ hotline để thiết kế tour riêng (Tour Private) theo yêu cầu.",
    },
  ];

  return (
    <div className="lg:col-span-8 space-y-8">
      {/* General Highlight Stats */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
        <div className="flex items-center gap-3">
          <div className="p-2.5 bg-primary-50 rounded-lg text-primary-600">
            <ClockIcon className="w-5 h-5" />
          </div>
          <div>
            <p className="text-[10px] uppercase font-bold text-gray-400 tracking-wider">
              Thời gian
            </p>
            <p className="text-sm font-bold text-gray-800">
              {tour.number_of_days} ngày / {tour.number_of_nights} đêm
            </p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <div className="p-2.5 bg-primary-50 rounded-lg text-primary-600">
            <MapPinIcon className="w-5 h-5" />
          </div>
          <div>
            <p className="text-[10px] uppercase font-bold text-gray-400 tracking-wider">
              Điểm đi
            </p>
            <p className="text-sm font-bold text-gray-800 truncate max-w-[110px]">
              {tour.start_location}
            </p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <div className="p-2.5 bg-primary-50 rounded-lg text-primary-600">
            <CompassIcon className="w-5 h-5" />
          </div>
          <div>
            <p className="text-[10px] uppercase font-bold text-gray-400 tracking-wider">
              Điểm đến
            </p>
            <p className="text-sm font-bold text-gray-800 truncate max-w-[110px]">
              {tour.end_location || "Đang cập nhật"}
            </p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <div className="p-2.5 bg-primary-50 rounded-lg text-primary-600">
            <svg
              className="w-5 h-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
              />
            </svg>
          </div>
          <div>
            <p className="text-[10px] uppercase font-bold text-gray-400 tracking-wider">
              Giới hạn
            </p>
            <p className="text-sm font-bold text-gray-800">
              {selectedSchedule ? `${selectedSchedule.max_people} khách` : "--"}
            </p>
          </div>
        </div>
      </div>

      {/* Giới thiệu */}
      <div className="bg-white rounded-xl p-6 md:p-8 border border-gray-100 shadow-sm">
        <h2 className="text-xl md:text-2xl font-bold text-gray-900 mb-4 font-plus-jakarta">
          Giới thiệu Tour
        </h2>
        <div className="text-gray-600 leading-relaxed text-sm md:text-base space-y-4 whitespace-pre-line font-inter">
          {tour.description ||
            "Tour du lịch trọn gói cao cấp được thiết kế riêng với các điểm đến nổi tiếng, dịch vụ chăm sóc chu đáo, nghỉ dưỡng tại khách sạn sang trọng và lịch trình được nghiên cứu khoa học đem lại trải nghiệm thư thái tuyệt vời cho du khách."}
        </div>
      </div>

      {/* Dịch vụ tiện ích */}
      <div className="bg-white rounded-xl p-6 md:p-8 border border-gray-100 shadow-sm">
        <h2 className="text-xl md:text-2xl font-bold text-gray-900 mb-6 font-plus-jakarta">
          Dịch vụ & Tiện ích đi kèm
        </h2>

        {/* Thông tin xe đi kèm */}
        {tour.vehicle_info && (
          <div className="mb-4 flex items-center gap-3 p-3.5 bg-amber-50 border border-amber-100 rounded-lg">
            <svg
              className="w-5 h-5 shrink-0 text-amber-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"
              />
            </svg>
            <div>
              <p className="text-xs font-bold uppercase text-amber-600 tracking-wide">Phương tiện di chuyển</p>
              <p className="text-sm font-semibold text-gray-800 mt-0.5">{tour.vehicle_info}</p>
            </div>
          </div>
        )}

        {tour.services?.length ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {tour.services.map((service: Service) => (
              <div
                key={service.id}
                className="flex items-start gap-3.5 p-3.5 bg-gray-50 border border-gray-100 rounded-lg hover:bg-primary-50/20 transition-all duration-300 group"
              >
                <div className="p-2 bg-white rounded-xl shadow-xs group-hover:scale-105 transition-transform duration-300 shrink-0">
                  {getServiceIcon(service.name)}
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-bold text-gray-800">
                    {service.name}
                  </p>
                  {service.description && (
                    <p className="text-xs text-gray-400 mt-0.5 leading-relaxed">
                      {service.description}
                    </p>
                  )}
                  {/*
                    Giá tham khảo, do quản trị nhập. Không cộng vào tiền đơn — dịch vụ này đã nằm
                    trong giá tour rồi.

                    Bỏ dấu cộng, thêm chữ "Trị giá", và trả màu về xám. Cả ba đều nói cùng một
                    điều: đây là thứ khách ĐƯỢC, không phải thứ khách PHẢI TRẢ THÊM. Một con số
                    trần màu thương hiệu trên trang bán hàng thì ai cũng đọc thành giá phải trả.
                  */}
                  {service.price != null && (
                    <p className="text-xs font-medium text-gray-500 mt-1">
                      Trị giá {Number(service.price).toLocaleString("vi-VN")}đ / khách
                    </p>
                  )}
                </div>
              </div>
            ))}
          </div>
        ) : (
          <p className="text-gray-400 text-sm">
            Chưa cập nhật danh sách dịch vụ đi kèm.
          </p>
        )}
      </div>

      {/*
        Bỏ khối "Hướng dẫn viên đồng hành".

        Khách chọn tour theo điểm đến, lịch trình và giá — không theo tên người dẫn. Và tên đó
        chưa chắc đúng: hướng dẫn viên phân công lại được tới sát ngày, có khi đổi giữa chuyến,
        nên in một cái tên lên trang bán hàng là hứa một thứ không giữ được.

        Điều hành vẫn thấy đủ ở màn quản lý chuyến; khách gặp ai thì biết khi lên xe.
      */}

      {/* Lịch trình (Accordion) */}
      {/*
        Lịch khởi hành đứng TRƯỚC lịch trình từng ngày.

        Người đọc trang này đang trả lời hai câu theo thứ tự: "đi ngày nào được" rồi mới tới "ngày
        ấy đi những đâu". Đặt ngược lại thì phải cuộn qua ba ngày hành trình mới thấy chỗ chọn
        ngày, mà chọn ngày mới là việc dẫn tới nút Đặt tour.
      */}
      <TourDepartures
        tour={tour}
        selectedSchedule={selectedSchedule}
        onScheduleChange={onScheduleChange}
      />

      <div className="bg-white rounded-xl p-6 md:p-8 border border-gray-100 shadow-sm">
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-xl md:text-2xl font-bold text-gray-900 font-plus-jakarta">
            Lịch trình di chuyển chi tiết
          </h2>
          <span className="text-xs text-primary-600 bg-primary-50 px-3 py-1 rounded-full font-semibold border border-primary-100">
            {tour.number_of_days} Ngày
          </span>
        </div>

        <div className="relative border-l-2 border-primary-100 ml-4 pl-6 md:pl-8 space-y-6">
          {tour.itineraries?.length ? (
            tour.itineraries.map((item: TourItinerary) => {
              const isExpanded = expandedDay === item.day_number;
              return (
                <div key={item.id} className="relative">
                  {/* Timeline Point */}
                  <div
                    onClick={() => toggleDay(item.day_number)}
                    className={`absolute -left-[41px] md:-left-[49px] top-1.5 w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs md:text-sm cursor-pointer shadow-md transition-all duration-305 ${
                      isExpanded
                        ? "bg-primary-600 text-white ring-4 ring-primary-100"
                        : "bg-white text-primary-600 border-2 border-primary-200 hover:bg-primary-50"
                    }`}
                  >
                    {item.day_number}
                  </div>

                  {/* Accordion Card */}
                  <div className="border border-gray-100 bg-gray-50/50 rounded-lg overflow-hidden transition-all duration-300">
                    <button
                      type="button"
                      onClick={() => toggleDay(item.day_number)}
                      className="w-full px-5 py-4 flex items-center justify-between text-left focus:outline-none hover:bg-gray-55 transition-colors"
                    >
                      <h3 className="font-bold text-sm md:text-base text-gray-900 pr-4">
                        Ngày {item.day_number}: {item.title}
                      </h3>
                      <svg
                        className={`w-4 h-4 text-gray-400 shrink-0 transform transition-transform duration-300 ${
                          isExpanded ? "rotate-180" : ""
                        }`}
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2.5}
                          d="M19 9l-7 7-7-7"
                        />
                      </svg>
                    </button>

                    {/* Content Section */}
                    <div
                      className={`transition-all duration-500 overflow-hidden ${
                        isExpanded
                          ? "max-h-[720px] opacity-100 border-t border-gray-100"
                          : "max-h-0 opacity-0"
                      }`}
                    >
                      <div className="space-y-4 p-5 text-sm md:text-base">
                        {(item.start_point || item.end_point || item.route_points || item.rest_stops) && (
                          <div className="grid grid-cols-1 gap-3 rounded-lg border border-gray-100 bg-white p-4 text-sm sm:grid-cols-2">
                            {item.start_point && (
                              <div>
                                <p className="text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                  Điểm đầu
                                </p>
                                <p className="mt-1 font-semibold text-gray-800">{item.start_point}</p>
                              </div>
                            )}
                            {item.end_point && (
                              <div>
                                <p className="text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                  Điểm đến
                                </p>
                                <p className="mt-1 font-semibold text-gray-800">{item.end_point}</p>
                              </div>
                            )}
                            {item.route_points && (
                              <div className="sm:col-span-2">
                                <p className="text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                  Chặng đi qua
                                </p>
                                <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                  {item.route_points
                                    .split(/[,\n]/)
                                    .map((point) => point.trim())
                                    .filter(Boolean)
                                    .map((point, pointIndex, points) => (
                                      <React.Fragment key={pointIndex}>
                                        <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">
                                          {point}
                                        </span>
                                        {pointIndex < points.length - 1 && (
                                          <svg
                                            className="h-3.5 w-3.5 shrink-0 text-gray-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                          >
                                            <path
                                              strokeLinecap="round"
                                              strokeLinejoin="round"
                                              strokeWidth={2.5}
                                              d="M9 5l7 7-7 7"
                                            />
                                          </svg>
                                        )}
                                      </React.Fragment>
                                    ))}
                                </div>
                              </div>
                            )}
                            {item.rest_stops && (
                              <div>
                                <p className="text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                  Nghỉ chân
                                </p>
                                <p className="mt-1 whitespace-pre-line text-gray-600">{item.rest_stops}</p>
                              </div>
                            )}
                          </div>
                        )}
                        <div className="text-gray-600 leading-relaxed whitespace-pre-line line-clamp-[12]">
                          {item.content}
                        </div>

                        {/*
                          Khối mở ra ở trên bị chặn chiều cao ở 720px, nên ngày nào viết dài là bị
                          cắt cụt mà không có đường xem tiếp — chữ mất hẳn chứ không phải cuộn được.
                          Nút này mở đúng nội dung ấy trong một hộp thoại không giới hạn chiều cao.
                        */}
                        <button
                          type="button"
                          onClick={() => setNgayDangMo(item)}
                          className="rounded-full border border-primary-200 bg-white px-4 py-2 text-xs font-semibold text-primary-600 transition-colors hover:bg-primary-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        >
                          Xem chi tiết ngày {item.day_number}
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              );
            })
          ) : (
            <p className="text-gray-400 text-sm">
              Lịch trình đang được xây dựng.
            </p>
          )}
        </div>
      </div>

      {/* Các câu hỏi thường gặp */}
      <div className="bg-white rounded-xl p-6 md:p-8 border border-gray-100 shadow-sm space-y-6">
        <h2 className="text-xl md:text-2xl font-bold text-gray-900 font-plus-jakarta">
          Các câu hỏi thường gặp (FAQs)
        </h2>

        <div className="space-y-3">
          {faqs.map((faq, idx) => {
            const isExpanded = expandedFaq === idx;
            return (
              <div
                key={idx}
                className="border border-gray-100 rounded-lg overflow-hidden bg-white"
              >
                <button
                  type="button"
                  onClick={() => toggleFaq(idx)}
                  className="w-full px-5 py-4 flex items-center justify-between text-left focus:outline-none hover:bg-gray-50 transition-colors"
                >
                  <span className="font-bold text-sm text-gray-800 pr-4">
                    {faq.q}
                  </span>
                  <svg
                    className={`w-4 h-4 text-gray-400 shrink-0 transform transition-transform duration-300 ${
                      isExpanded ? "rotate-180" : ""
                    }`}
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2.5}
                      d="M19 9l-7 7-7-7"
                    />
                  </svg>
                </button>
                <div
                  className={`transition-all duration-300 overflow-hidden ${
                    isExpanded
                      ? "max-h-40 opacity-100 border-t border-gray-100"
                      : "max-h-0 opacity-0"
                  }`}
                >
                  <p className="p-5 text-sm text-gray-600 leading-relaxed bg-gray-50">
                    {faq.a}
                  </p>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Vị trí và điểm đón khách */}
      <div className="bg-white rounded-xl p-6 md:p-8 border border-gray-100 shadow-sm space-y-6">
        <h2 className="text-xl md:text-2xl font-bold text-gray-900 font-plus-jakarta">
          Điểm đón & Thông tin di chuyển
        </h2>
        <div className="space-y-4 text-sm">
          <div className="flex gap-3">
            <MapPinIcon className="w-5 h-5 text-primary-600 shrink-0 mt-0.5" />
            <div>
              <p className="font-bold text-gray-800">Điểm đón khách:</p>
              <p className="text-gray-500 mt-0.5">
                {tour.pickup_location ||
                  `Tập trung tại ${tour.start_location}. Chi tiết điểm đón sẽ được gửi qua email sau khi đặt tour.`}
              </p>
            </div>
          </div>
          <div className="flex gap-3">
            <CompassIcon className="w-5 h-5 text-primary-600 shrink-0 mt-0.5" />
            <div>
              <p className="font-bold text-gray-800">Hành trình:</p>
              <p className="text-gray-500 mt-0.5">
                {tour.start_location}
                {tour.end_location ? ` → ${tour.end_location}` : ""} ·{" "}
                {tour.number_of_days} ngày {tour.number_of_nights} đêm
              </p>
            </div>
          </div>
          {tour.vehicle_info && (
            <div className="flex gap-3">
              <svg
                className="w-5 h-5 text-primary-600 shrink-0 mt-0.5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"
                />
              </svg>
              <div>
                <p className="font-bold text-gray-800">Phương tiện di chuyển:</p>
                <p className="text-gray-500 mt-0.5">{tour.vehicle_info}</p>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Chính sách và điều khoản */}
      <div className="bg-white rounded-xl p-6 md:p-8 border border-gray-100 shadow-sm space-y-6">
        <h2 className="text-xl md:text-2xl font-bold text-gray-900 font-plus-jakarta">
          Chính sách & Quy định của Vivu Booking
        </h2>

        <div className="grid md:grid-cols-2 gap-6 text-sm">
          <div className="space-y-3">
            <h4 className="font-bold text-gray-800 flex items-center gap-1.5">
              <span className="w-1.5 h-6 bg-primary-600 rounded-full"></span>
              Giá tour bao gồm
            </h4>
            {/*
              Danh sách dịch vụ THẬT của tour, lấy từ dữ liệu quản trị nhập.

              Bản cũ ở đây là một danh sách viết cứng giống hệt nhau cho mọi tour — kể cả những
              dòng hứa hẹn cụ thể như "bảo hiểm du lịch mức bồi thường tối đa 50.000.000đ/vụ",
              một cam kết bằng con số cho thứ hệ thống không hề quản lý. Mà ngay phía trên trang
              này đã có khối dịch vụ đọc từ `tour.services`, nên cùng một trang nói hai điều khác
              nhau về cùng một câu hỏi.
            */}
            {tour.services && tour.services.length > 0 ? (
              <ul className="list-disc pl-5 text-gray-500 space-y-1.5">
                {tour.services.map((service: Service) => (
                  <li key={service.id}>
                    {service.name}
                    {service.description ? ` — ${service.description}` : ""}
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-gray-400 italic">
                Chưa cập nhật danh sách dịch vụ đi kèm. Vui lòng liên hệ tổng đài để được tư vấn
                chi tiết.
              </p>
            )}
          </div>

          <div className="space-y-3">
            <h4 className="font-bold text-gray-800 flex items-center gap-1.5">
              <span className="w-1.5 h-6 bg-red-500 rounded-full"></span>
              Chính sách hoàn hủy
              {policy?.cancellation.name && (
                <span className="text-xs font-medium text-gray-400">
                  · {policy.cancellation.name}
                </span>
              )}
            </h4>

            {bacHoan.length > 0 ? (
              <>
                <ul className="list-disc pl-5 text-gray-500 space-y-1.5">
                  {bacHoan.map((bac) => (
                    <li key={bac.window}>
                      {bac.window}: hoàn <strong className="text-gray-700">{bac.refund_percent}%</strong>
                      {bac.note ? ` — ${bac.note}` : ""}
                    </li>
                  ))}
                </ul>
                <p className="text-xs text-gray-400 leading-relaxed">
                  Phí hủy tính trên giá trị đơn, tiền hoàn trừ trên số tiền đã thanh toán. Hủy
                  không bao giờ phát sinh khoản phải nộp thêm. Điều khoản áp dụng cho đơn của bạn
                  là điều khoản có hiệu lực tại thời điểm đặt, sửa về sau không hồi tố.
                </p>
              </>
            ) : (
              <p className="text-gray-400 italic">Đang tải chính sách hoàn hủy...</p>
            )}
          </div>
        </div>
      </div>

      {/* Đánh giá và bình luận tour công khai */}
      <TourReviewsSection
      tourId={tour.id}
      tourTitle={tour.title}
    />

      {/*
        Hộp thoại chi tiết một ngày.

        Đóng được bằng ba đường vì người ta quen ba đường khác nhau: bấm dấu X, bấm ra ngoài nền
        mờ, và bấm Esc. Thiếu đường thứ ba là chỗ hay quên nhất, và nó là đường duy nhất của người
        dùng bàn phím.

        Nội dung ở đây KHÔNG bị chặn chiều cao, khác khối mở ra trong danh sách. Đó chính là lý do
        hộp thoại này tồn tại: ngày nào viết dài thì trong danh sách bị cắt cụt, còn ở đây đọc hết.
      */}
      {ngayDangMo && (
        <div
          className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 py-10"
          role="dialog"
          aria-modal="true"
          aria-label={`Lịch trình ngày ${ngayDangMo.day_number}`}
          onClick={() => setNgayDangMo(null)}
        >
          <div
            className="w-full max-w-2xl rounded-2xl bg-white shadow-xl"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-5">
              <h3 className="font-plus-jakarta text-xl font-bold text-gray-900">Lịch trình</h3>
              <button
                type="button"
                onClick={() => setNgayDangMo(null)}
                aria-label="Đóng"
                className="rounded-full border border-gray-200 p-2 text-gray-500 transition-colors hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
              >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div className="px-6 py-5">
              <div className="rounded-xl bg-primary-50 px-5 py-4">
                <p className="font-plus-jakarta text-lg font-bold text-primary-700">
                  Ngày {ngayDangMo.day_number}
                </p>
                <p className="mt-1 text-base font-semibold text-gray-900">{ngayDangMo.title}</p>
                {(ngayDangMo.start_point || ngayDangMo.end_point) && (
                  <p className="mt-1.5 text-sm text-gray-600">
                    {[ngayDangMo.start_point, ngayDangMo.end_point].filter(Boolean).join(" → ")}
                  </p>
                )}
              </div>

              {ngayDangMo.rest_stops && (
                <p className="mt-4 text-sm text-gray-600">
                  <span className="font-semibold text-gray-800">Nghỉ chân:</span>{" "}
                  {ngayDangMo.rest_stops}
                </p>
              )}

              <div className="mt-4 whitespace-pre-line leading-relaxed text-gray-700">
                {ngayDangMo.content}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

