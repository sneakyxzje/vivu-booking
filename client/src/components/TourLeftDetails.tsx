import React, { useState } from "react";
import type { Tour, Service, TourItinerary } from "@/types";
import {
  MapPinIcon,
  ClockIcon,
  CompassIcon,
  HotelIcon,
  LandmarkIcon,
} from "@/components/Icons";
import { TourReviewsSection } from "@/components/TourReviewsSection";

interface TourLeftDetailsProps {
  tour: Tour;
  selectedSchedule: any;
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
}) => {
  const [expandedDay, setExpandedDay] = useState<number | null>(1);
  const [expandedFaq, setExpandedFaq] = useState<number | null>(null);

  const toggleDay = (dayNum: number) => {
    setExpandedDay(expandedDay === dayNum ? null : dayNum);
  };

  const toggleFaq = (idx: number) => {
    setExpandedFaq(expandedFaq === idx ? null : idx);
  };

  const faqs = [
    {
      q: "Giá tour hiển thị đã bao gồm những chi phí gì?",
      a: "Giá tour hiển thị đã trọn gói bao gồm: xe du lịch máy lạnh đưa đón khứ hồi, lưu trú tiêu chuẩn khách sạn/resort cao cấp, các bữa ăn ngon đặc sản địa phương theo lịch trình, vé vào cổng tham quan lần 1 tại các điểm trong lịch trình và hướng dẫn viên đồng hành nhiệt tình suốt tuyến du lịch.",
    },
    {
      q: "Quy định về việc hủy đặt tour và hoàn tiền như thế nào?",
      a: "Bạn có thể hủy tour hoàn toàn miễn phí nếu thực hiện trước ngày khởi hành tối thiểu 15 ngày. Nếu hủy muộn hơn, chúng tôi sẽ khấu trừ theo tỷ lệ được quy định rõ trong phần chính sách hoàn hủy của tour (từ 30% đến 100% tùy mốc thời gian).",
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
            <span className="text-xl shrink-0">🚌</span>
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
                  {service.icon ? (
                    <span className="text-xl leading-none">{service.icon}</span>
                  ) : (
                    getServiceIcon(service.name)
                  )}
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
                  {/* Hiển thị giá nếu dịch vụ có phí phát sinh riêng */}
                  {service.price != null && (
                    <p className="text-xs font-semibold text-primary-600 mt-1">
                      +{Number(service.price).toLocaleString("vi-VN")}đ / khách
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

      {/* Hướng dẫn viên đồng hành */}
      <div className="bg-white rounded-xl p-6 md:p-8 border border-gray-100 shadow-sm">
        <h2 className="text-xl md:text-2xl font-bold text-gray-900 mb-6 font-plus-jakarta">
          Hướng dẫn viên đồng hành
        </h2>
        <div className="flex flex-col sm:flex-row items-center gap-6 p-5 bg-primary-50/20 border border-primary-100/50 rounded-lg">
          <div className="w-20 h-20 rounded-full overflow-hidden shrink-0 border-2 border-white shadow-md">
            <img
              src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=150&h=150&q=80"
              alt="Tour Guide"
              className="w-full h-full object-cover"
            />
          </div>
          <div className="flex-1 text-center sm:text-left">
            <div className="flex flex-col sm:flex-row sm:items-center gap-2 mb-2">
              <h4 className="font-bold text-gray-900 text-base">Trần Minh Quân</h4>
              <span className="inline-block px-2.5 py-0.5 bg-emerald-50 text-emerald-700 text-xs font-semibold rounded-full border border-emerald-200 w-fit mx-auto sm:mx-0">
                HDV Quốc Tế
              </span>
            </div>
            <p className="text-xs text-gray-500 font-medium mb-3 flex items-center justify-center sm:justify-start gap-3 font-mono">
              <span className="font-inter">★ 5.0 (24 bình luận)</span>
              <span>• 5 năm kinh nghiệm</span>
              <span>• Ngôn ngữ: Tiếng Việt, Tiếng Anh</span>
            </p>
            <p className="text-sm text-gray-600 italic leading-relaxed">
              "Tôi sẽ cùng đồng hành với mọi người trong suốt chuyến hành trình này, chia sẻ những câu chuyện văn hóa lịch sử bản địa thú vị và sẵn sàng hỗ trợ đoàn bất kỳ lúc nào."
            </p>
          </div>
        </div>
      </div>

      {/* Lịch trình (Accordion) */}
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
                        <div className="text-gray-600 leading-relaxed whitespace-pre-line">
                          {item.content}
                        </div>
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
          Vị trí & Điểm đón khách khởi hành
        </h2>
        <div className="space-y-4">
          <div className="relative h-[240px] rounded-lg overflow-hidden border border-gray-200/60 shadow-inner group">
            <img
              src="https://images.unsplash.com/photo-1524661135-423995f22d0b?auto=format&fit=crop&w=800&q=80"
              alt="Map"
              className="w-full h-full object-cover"
            />
            <div className="absolute inset-0 bg-black/10"></div>
            <div className="absolute bottom-4 left-4 bg-white/90 backdrop-blur-xs px-3 py-1.5 rounded-lg border border-gray-200/60 flex items-center gap-1.5 text-xs font-bold text-gray-800">
              <MapPinIcon className="w-4 h-4 text-primary-600" />
              <span>Xem bản đồ chi tiết trên Google Maps</span>
            </div>
          </div>

          <div className="space-y-3.5 text-sm">
            <div className="flex gap-3">
              <div className="w-5 h-5 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center shrink-0 font-bold text-xs mt-0.5 font-mono">
                A
              </div>
              <div>
                <p className="font-bold text-gray-800">Điểm đón khách chính:</p>
                <p className="text-gray-500 text-xs mt-0.5">
                  Nhà hát Lớn Hà Nội - Số 1 Tràng Tiền, Hoàn Kiếm, Hà Nội (Dành cho
                  đoàn khởi hành từ miền Bắc).
                </p>
              </div>
            </div>
            <div className="flex gap-3">
              <div className="w-5 h-5 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center shrink-0 font-bold text-xs mt-0.5 font-mono">
                B
              </div>
              <div>
                <p className="font-bold text-gray-800">Điểm đón khách phụ:</p>
                <p className="text-gray-500 text-xs mt-0.5">
                  Ga Quốc Tế - Cột số 9, Sân bay Tân Sơn Nhất, TP. Hồ Chí Minh
                  (Dành cho đoàn khởi hành từ miền Nam).
                </p>
              </div>
            </div>
          </div>
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
            <ul className="list-disc pl-5 text-gray-500 space-y-1.5">
              <li>
                Vé máy bay/Phương tiện di chuyển đời mới khứ hồi theo lịch trình.
              </li>
              <li>Nghỉ dưỡng tiêu chuẩn khách sạn hoặc resort sang trọng.</li>
              <li>Các bữa ăn chất lượng theo thực đơn đặc sản vùng miền.</li>
              <li>Vé vào cổng các điểm tham quan đã bao gồm trong chương trình.</li>
              <li>Hướng dẫn viên kinh nghiệm, năng động nhiệt tình suốt tuyến.</li>
              <li>Bảo hiểm du lịch mức bồi thường tối đa 50.000.000đ/vụ.</li>
            </ul>
          </div>

          <div className="space-y-3">
            <h4 className="font-bold text-gray-800 flex items-center gap-1.5">
              <span className="w-1.5 h-6 bg-red-500 rounded-full"></span>
              Chính sách hoàn hủy
            </h4>
            <ul className="list-disc pl-5 text-gray-500 space-y-1.5">
              <li>Hủy trước 15 ngày khởi hành: Miễn phí hoàn toàn.</li>
              <li>
                Hủy từ 8 đến 14 ngày trước khởi hành: Phí hủy 30% giá tour.
              </li>
              <li>
                Hủy từ 4 đến 7 ngày trước khởi hành: Phí hủy 50% giá tour.
              </li>
              <li>
                Hủy trong vòng 3 ngày trước khởi hành: Phí hủy 100% giá tour.
              </li>
              <li>Các dịp Lễ, Tết áp dụng chính sách riêng biệt.</li>
            </ul>
          </div>
        </div>
      </div>

      {/* Đánh giá và bình luận tour công khai */}
      <TourReviewsSection 
      tourId={tour.id}
      tourTitle={tour.title}
    />
    </div>
  );
};

