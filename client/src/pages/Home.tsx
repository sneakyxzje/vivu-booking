import React, { useState, useEffect } from "react";
import { Link, useNavigate } from "react-router-dom";
import tourService from "@/services/tourService";
import api from "@/services/api";
import type { Tour } from "@/types";
import { TourCard } from "@/components/TourCard";
import { useDocumentMeta } from "@/hooks/useDocumentMeta";

import {
  MapPinIcon,
  PlaneIcon,
  ClockIcon,
  CompassIcon,
  BeachWavesIcon,
  MountainIcon,
  LandmarkIcon,
  HotelIcon,
  MagnifyingGlassIcon,
  ExclamationTriangleIcon,
  EnvelopeIcon,
  CurrencyDollarIcon,
  ShieldCheckIcon,
  SupportIcon,
  CreditCardIcon,
} from "@/components/Icons";

// ── QUICK CATEGORY CONFIG ───────────────────────────────────────────────────────

interface QuickCategory {
  id: string;
  label: string;
  icon: React.ReactNode;
}

const QUICK_CATEGORIES: QuickCategory[] = [
  { id: "all", label: "Tất cả", icon: <CompassIcon /> },
  { id: "tour-bien-dao", label: "Biển đảo", icon: <BeachWavesIcon /> },
  { id: "tour-leo-nui", label: "Leo núi / Trekking", icon: <MountainIcon /> },
  { id: "tour-van-hoa", label: "Văn hóa / Lịch sử", icon: <LandmarkIcon /> },
  { id: "tour-nghi-duong", label: "Nghỉ dưỡng", icon: <HotelIcon /> },
];

// ── BRAND VALUES CONFIG ─────────────────────────────────────────────────────────

interface BrandValue {
  icon: React.ReactNode;
  title: string;
  desc: string;
}

const BRAND_VALUES: BrandValue[] = [
  {
    icon: <CurrencyDollarIcon className="w-8 h-8 text-primary-600" />,
    title: "Đảm bảo giá tốt nhất",
    desc: "Chúng tôi luôn cam kết mức giá cạnh tranh và nhiều ưu đãi hấp dẫn nhất cho khách hàng.",
  },
  {
    icon: <SupportIcon className="w-8 h-8 text-primary-600" />,
    title: "Dịch vụ hỗ trợ 24/7",
    desc: "Đội ngũ chuyên viên tư vấn hỗ trợ giải quyết khó khăn, thắc mắc mọi lúc mọi nơi.",
  },
  {
    icon: <ShieldCheckIcon className="w-8 h-8 text-primary-600" />,
    title: "Tour tuyển chọn chất lượng",
    desc: "Mọi tour đều được chọn lọc kỹ càng từ các đơn vị lữ hành uy tín, chuyên nghiệp.",
  },
  {
    icon: <CreditCardIcon className="w-8 h-8 text-primary-600" />,
    title: "Thanh toán an toàn, linh hoạt",
    desc: "Tích hợp đa cổng thanh toán: chuyển khoản, ví điện tử đến thẻ quốc tế bảo mật.",
  },
];

// ── TESTIMONIALS (đánh giá thật từ khách hàng, tải theo tour) ───────────────────

interface HomeTestimonial {
  name: string;
  role: string;
  stars: number;
  comment: string;
}

// ── SECTION HEADING ─────────────────────────────────────────────────────────────

interface SectionHeadingProps {
  title: string;
  subtitle?: string;
}

const SectionHeading: React.FC<SectionHeadingProps> = ({ title, subtitle }) => (
  <div className="text-center max-w-xl mx-auto mb-12">
    <h2 className="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight">
      {title}
    </h2>
    {subtitle && <p className="text-sm text-gray-500 mt-2">{subtitle}</p>}
  </div>
);

// ── TOUR GRID STATES ────────────────────────────────────────────────────────────

const TourGridSkeleton: React.FC = () => (
  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
    {[1, 2, 3, 4].map((n) => (
      <div
        key={n}
        className="bg-white rounded-xl overflow-hidden border border-gray-100 shadow-sm animate-pulse"
      >
        <div className="h-52 bg-gray-200" />
        <div className="p-5 space-y-4">
          <div className="h-4 bg-gray-200 rounded w-1/3" />
          <div className="h-6 bg-gray-200 rounded w-5/6" />
          <div className="h-4 bg-gray-200 rounded w-1/2" />
          <div className="flex justify-between items-center pt-2">
            <div className="h-5 bg-gray-200 rounded w-1/4" />
            <div className="h-9 bg-gray-200 rounded w-1/3" />
          </div>
        </div>
      </div>
    ))}
  </div>
);

interface TourGridErrorProps {
  message: string;
  onRetry: () => void;
}

const TourGridError: React.FC<TourGridErrorProps> = ({ message, onRetry }) => (
  <div className="text-center py-16 bg-white rounded-xl border border-gray-100 shadow-sm max-w-lg mx-auto flex flex-col items-center">
    <ExclamationTriangleIcon className="w-12 h-12 text-red-400 mb-4" />
    <h3 className="text-lg font-bold text-gray-800">Tải dữ liệu thất bại</h3>
    <p className="text-sm text-gray-500 mt-2">{message}</p>
    <button
      onClick={onRetry}
      className="mt-6 inline-flex items-center gap-2 bg-primary-600 text-white font-semibold text-sm px-5 py-2.5 rounded-full hover:bg-primary-700 transition-colors cursor-pointer"
    >
      Thử lại
    </button>
  </div>
);

/*
 * `TourGridEmpty` đã bị bỏ cùng với phép lọc tại chỗ.
 *
 * Nó tồn tại để nói "không tìm thấy tour phù hợp, hãy xóa bộ lọc" — một câu chỉ có nghĩa khi trang
 * chủ tự lọc lấy. Giờ mọi phép lọc dẫn sang `/tours`, nên trang chủ không còn trạng thái "lọc xong
 * không còn gì"; nó chỉ có thể trống khi công ty thật sự chưa có tour nào đang bán.
 */

// ── HOME PAGE ───────────────────────────────────────────────────────────────────

export const Home: React.FC = () => {
  useDocumentMeta({
    title: "Vivu Booking",
    description:
      "Đặt tour du lịch trong nước: lịch khởi hành rõ ràng, giá theo từng loại khách, chính sách hủy công khai trước khi đặt.",
  });

  const navigate = useNavigate();
  const [tours, setTours] = useState<Tour[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  // Bộ lọc tìm kiếm
  const [searchDest, setSearchDest] = useState<string>("");
  const [searchStart, setSearchStart] = useState<string>("");
  const [selectedCategory, setSelectedCategory] = useState<string>("all");
  const [selectedDuration, setSelectedDuration] = useState<string>("all");

  const [newsletterEmail, setNewsletterEmail] = useState("");
  const [newsletterSubmitting, setNewsletterSubmitting] = useState(false);
  const [newsletterDone, setNewsletterDone] = useState(false);

  const [testimonials, setTestimonials] = useState<HomeTestimonial[]>([]);

  // Lấy các đánh giá thật (điểm cao nhất) từ những tour đã có nhận xét
  useEffect(() => {
    const reviewedTours = tours
      .filter((tour) => (tour.review_count ?? 0) > 0)
      .slice(0, 3);

    if (reviewedTours.length === 0) return;

    Promise.all(
      reviewedTours.map(async (tour) => {
        try {
          const res = await tourService.getReviews(tour.id);
          return (res.data ?? [])
            .slice(0, 2)
            .map((item) => ({
              name: item.user?.name ?? "Khách hàng Vivu",
              role: `Đã tham gia ${tour.title}`,
              stars: Number(item.rating) || 5,
              comment: item.comment,
            }));
        } catch {
          return [];
        }
      }),
    ).then((groups) => {
      const seenNames = new Set<string>();
      setTestimonials(
        groups
          .flat()
          .sort((a, b) => b.stars - a.stars)
          .filter((item) => {
            if (seenNames.has(item.name)) return false;
            seenNames.add(item.name);
            return true;
          })
          .slice(0, 3),
      );
    });
  }, [tours]);

  const handleNewsletterSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newsletterEmail.trim()) return;
    setNewsletterSubmitting(true);
    try {
      await api.post("/newsletter", { email: newsletterEmail.trim() });
      setNewsletterDone(true);
    } catch {
      setNewsletterDone(false);
    } finally {
      setNewsletterSubmitting(false);
    }
  };

  /**
   * Số tour trưng bày trên trang chủ.
   *
   * Trang chủ là cửa vào, không phải danh mục. Toàn bộ việc duyệt và lọc thuộc về `/tours`, nơi đã
   * có phân trang và lọc phía máy chủ đầy đủ.
   */
  const SO_TOUR_TRUNG_BAY = 8;

  const [tongSoTour, setTongSoTour] = useState(0);

  // ── DATA FETCHING ────────────────────────────────────────────────────────────

  /*
   * Xin đúng số tour cần trưng, và giữ lại `meta.total`.
   *
   * Trước đây lời gọi này không truyền tham số nào, nên máy chủ trả trang một với mười hai tour rồi
   * trang chủ vứt `meta` đi — tour thứ mười ba trở đi vô hình, và không có dòng nào nói là còn nữa.
   *
   * Giữ `total` để nút "Xem tất cả" nói được con số thật. Một lời mời ghi rõ "xem tất cả 56 tour"
   * khác hẳn một chữ "xem thêm" chung chung: nó cho người đọc biết cái họ đang nhìn chỉ là một phần.
   */
  /*
   * Không đặt trạng thái nào TRƯỚC lời gọi mạng.
   *
   * `loading` vốn khởi tạo là `true`, nên lần tải đầu không cần bật lại. Bật ở đây khiến hàm đổi
   * trạng thái ngay trong thân effect — React gọi đó là đổ thác và cảnh báo đúng: mỗi lần vào trang
   * sinh thừa một lượt vẽ lại. Nút thử lại thì cần, và nó tự lo lấy ở `thuLai()`.
   */
  const fetchTours = async () => {
    try {
      const res = await tourService.getAll({
        per_page: SO_TOUR_TRUNG_BAY,
        sort: "featured",
      });

      if (res.success) {
        setTours(res.data);
        setTongSoTour(res.meta.total);
        setError(null);
      } else {
        setError("Không thể tải danh sách tour du lịch.");
      }
    } catch (err) {
      setError("Đã xảy ra lỗi trong quá trình kết nối máy chủ.");
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const thuLai = () => {
    setLoading(true);
    void fetchTours();
  };

  useEffect(() => {
    void fetchTours();
  }, []);

  /*
   * ── MỌI PHÉP LỌC ĐỀU DẪN SANG /tours ─────────────────────────────────────────
   *
   * Trước đây màn này có hai kiểu lọc ngược nhau. Ô tìm kiếm chính chuyển sang `/tours` để máy chủ
   * lọc trên toàn bộ danh mục; còn các chip danh mục và số ngày thì lọc ngay tại chỗ, trong đúng
   * mười hai tour của trang một.
   *
   * Kiểu thứ hai hỏng theo cách khó thấy nhất: bấm chip "Khám phá" mà mọi tour thuộc danh mục ấy
   * nằm từ vị trí mười ba trở đi thì trang chủ báo "không tìm thấy" cho những tour đang bán bình
   * thường. Người dùng tin là không có, và đóng trang.
   *
   * Nên các chip giờ đi cùng một đường với ô tìm kiếm. Một kiểu lọc, chạy trên toàn bộ danh mục,
   * và không có bản sao nào để lệch nhau về sau.
   */
  const chuyenSangDanhMuc = (ghiDe?: { category?: string; duration?: string }) => {
    const category = ghiDe?.category ?? selectedCategory;
    const duration = ghiDe?.duration ?? selectedDuration;

    const params = new URLSearchParams();
    const keyword = searchDest.trim();
    const startLocation = searchStart.trim();

    if (keyword) params.set("q", keyword);
    if (startLocation) params.set("start_location", startLocation);
    if (duration !== "all") params.set("duration", duration);
    if (category !== "all") params.set("categories", category);

    const queryString = params.toString();
    navigate(queryString ? `/tours?${queryString}` : "/tours");
  };

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    chuyenSangDanhMuc();
  };

  const handleCategoryChange = (id: string) => {
    setSelectedCategory(id);
    chuyenSangDanhMuc({ category: id });
  };

  const handleDurationChange = (val: string) => {
    setSelectedDuration(val);
    chuyenSangDanhMuc({ duration: val });
  };

  // ── RENDER ───────────────────────────────────────────────────────────────────

  return (
    <div className="bg-gray-50 min-h-screen animate-fade-in pb-12">
      {/* ── 1. HERO ─────────────────────────────────────────────────────────── */}
      <section className="relative h-[550px] md:h-[620px] flex items-center justify-center bg-gray-900 overflow-hidden">
        <div className="absolute inset-0 z-0">
          <img
            src="https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1600&q=80"
            alt="Bãi biển Việt Nam"
            className="w-full h-full object-cover object-center opacity-55"
          />
          <div className="absolute inset-0 bg-gradient-to-t from-gray-900 via-transparent to-black/35" />
        </div>

        <div className="relative z-10 max-w-5xl mx-auto px-4 text-center">
          <span className="inline-block px-4 py-1.5 rounded-full bg-primary-500/20 text-blue-200 text-xs font-semibold tracking-wider uppercase mb-4 border border-blue-400/20 backdrop-blur-md">
            Mạng bán tour trực tuyến hàng đầu
          </span>
          <h1 className="text-4xl md:text-6xl font-bold text-white tracking-tight leading-none mb-6">
            Khám phá Việt Nam <br className="hidden md:inline" />
            <span className="text-blue-100">
              Cùng Vivu Booking
            </span>
          </h1>
          <p className="text-base md:text-lg text-gray-200 max-w-2xl mx-auto mb-10 leading-relaxed font-light">
            Tìm kiếm tour du lịch trọn gói, du thuyền cao cấp và trải nghiệm
            những vùng đất mới với mức giá ưu đãi nhất thị trường.
          </p>

          {/* Search Panel */}
          <div className="w-full max-w-4xl mx-auto bg-white/95 hover:bg-white rounded-xl shadow-2xl p-4 md:p-6 backdrop-blur-lg border border-white/20 transition-all duration-300">
            <form
              onSubmit={handleSearchSubmit}
              className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end"
            >
              {/* Điểm đến */}
              <div className="text-left">
                <label className="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 ml-1">
                  Điểm đến
                </label>
                <div className="flex items-center bg-gray-50 rounded-lg px-3 py-2.5 border border-gray-100 focus-within:border-primary-500 focus-within:bg-white transition-all">
                  <MapPinIcon className="w-5 h-5 mr-2 text-gray-400 shrink-0" />
                  <input
                    type="text"
                    value={searchDest}
                    onChange={(e) => setSearchDest(e.target.value)}
                    placeholder="Vịnh Hạ Long, Sapa..."
                    className="w-full bg-transparent border-none text-sm text-gray-800 focus:outline-none placeholder-gray-400 font-medium"
                  />
                </div>
              </div>

              {/* Khởi hành từ */}
              <div className="text-left">
                <label className="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 ml-1">
                  Khởi hành từ
                </label>
                <div className="flex items-center bg-gray-50 rounded-lg px-3 py-2.5 border border-gray-100 focus-within:border-primary-500 focus-within:bg-white transition-all">
                  <PlaneIcon className="w-5 h-5 mr-2 text-gray-400 shrink-0" />
                  <input
                    type="text"
                    value={searchStart}
                    onChange={(e) => setSearchStart(e.target.value)}
                    placeholder="Hà Nội, TP.HCM..."
                    className="w-full bg-transparent border-none text-sm text-gray-800 focus:outline-none placeholder-gray-400 font-medium"
                  />
                </div>
              </div>

              {/* Thời gian */}
              <div className="text-left">
                <label className="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 ml-1">
                  Thời gian
                </label>
                <div className="flex items-center bg-gray-50 rounded-lg px-3 py-2.5 border border-gray-100 focus-within:border-primary-500 focus-within:bg-white transition-all">
                  <ClockIcon className="w-5 h-5 mr-2 text-gray-400 shrink-0" />
                  <select
                    value={selectedDuration}
                    onChange={(e) => handleDurationChange(e.target.value)}
                    className="w-full bg-transparent border-none text-sm text-gray-800 focus:outline-none font-medium cursor-pointer"
                  >
                    <option value="all">Tất cả thời gian</option>
                    <option value="1">Trong ngày</option>
                    <option value="2-3">2 – 3 ngày</option>
                    <option value="4+">Từ 4 ngày trở lên</option>
                  </select>
                </div>
              </div>

              {/* Submit */}
              <button
                type="submit"
                className="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-lg py-3 px-6 shadow-lg shadow-primary-600/20 flex items-center justify-center gap-2 hover:scale-[1.02] active:scale-[0.98] transition-all cursor-pointer"
              >
                <MagnifyingGlassIcon className="w-5 h-5 text-white" />
                Tìm kiếm
              </button>
            </form>
          </div>
        </div>
      </section>

      {/* ── 2. CATEGORY TABS ────────────────────────────────────────────────── */}
      <section className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 mt-12 mb-8">
        <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-gray-200 pb-5">
          <div>
            <h2 className="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight">
              Lựa chọn điểm đến lý tưởng
            </h2>
            <p className="text-sm text-gray-500 mt-1">
              Khám phá các tour theo từng phong cách du lịch của riêng bạn
            </p>
          </div>

          <div className="flex gap-2 overflow-x-auto pb-2 w-full md:w-auto -mx-4 px-4 md:mx-0 md:px-0 scrollbar-none">
            {QUICK_CATEGORIES.map((cat) => {
              const active = selectedCategory === cat.id;
              return (
                <button
                  key={cat.id}
                  onClick={() => handleCategoryChange(cat.id)}
                  className={`group flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-semibold transition-all whitespace-nowrap cursor-pointer ${
                    active
                      ? "bg-primary-600 text-white shadow-lg shadow-primary-600/15 scale-105"
                      : "bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 hover:text-primary-600"
                  }`}
                >
                  <span
                    className={
                      active
                        ? "text-white"
                        : "text-gray-400 group-hover:text-primary-600 transition-colors"
                    }
                  >
                    {React.cloneElement(
                      cat.icon as React.ReactElement<{ className?: string }>,
                      {
                        className: "w-4 h-4",
                      },
                    )}
                  </span>
                  {cat.label}
                </button>
              );
            })}
          </div>
        </div>
      </section>

      {/* ── 3. TOUR GRID ────────────────────────────────────────────────────── */}
      <section className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 mb-16">
        {loading && <TourGridSkeleton />}

        {!loading && error && tours.length === 0 && (
          <TourGridError message={error} onRetry={thuLai} />
        )}

        {!loading && !error && tours.length === 0 && (
          <p className="rounded-xl border border-gray-200 bg-white py-12 text-center text-sm text-gray-500">
            Chưa có tour nào đang mở bán.
          </p>
        )}

        {!loading && tours.length > 0 && (
          <>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
              {tours.map((tour) => (
                <TourCard key={tour.id} tour={tour} />
              ))}
            </div>

            {/*
              Nói thẳng đây chỉ là một phần, và còn bao nhiêu nữa.

              Không có dòng này thì lưới tour trông như toàn bộ những gì công ty có — người đọc đếm
              được tám cái và kết luận là hết. Con số thật lấy từ `meta.total` của cùng lời gọi API,
              nên nó không thể lệch với thực tế.
            */}
            {tongSoTour > tours.length && (
              <div className="mt-10 text-center">
                <p className="text-sm text-gray-500">
                  Đang hiển thị {tours.length} trong tổng số{" "}
                  <span className="font-bold text-gray-900">{tongSoTour}</span> tour
                </p>
                <Link
                  to="/tours"
                  className="mt-4 inline-flex items-center gap-2 rounded-full border border-primary-200 bg-white px-8 py-3 text-sm font-bold text-primary-700 shadow-sm transition-all hover:bg-primary-50 hover:shadow-md"
                >
                  Xem tất cả {tongSoTour} tour
                  <span aria-hidden="true">→</span>
                </Link>
              </div>
            )}
          </>
        )}
      </section>

      <section className="bg-white border-y border-gray-100 py-16 mb-16">
        <div className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
          <SectionHeading
            title="Tại sao nên chọn Vivu Booking?"
            subtitle="Chúng tôi mang lại giá trị thiết thực và sự an tâm tuyệt đối cho mọi chuyến đi"
          />
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            {BRAND_VALUES.map((val, idx) => (
              <div
                key={idx}
                className="bg-gray-50/50 hover:bg-white border border-gray-100 hover:border-blue-100 hover:shadow-xl rounded-xl p-6 transition-all duration-300"
              >
                <div className="bg-primary-50 w-14 h-14 rounded-lg flex items-center justify-center mb-5 shadow-sm">
                  {val.icon}
                </div>
                <h3 className="font-bold text-gray-900 text-lg mb-2">
                  {val.title}
                </h3>
                <p className="text-gray-500 text-sm leading-relaxed">
                  {val.desc}
                </p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── 5. PROMO BANNER ─────────────────────────────────────────────────── */}
      <section className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 mb-16">
        <div className="bg-primary-700 rounded-xl overflow-hidden shadow-lg text-white relative py-12 px-6 md:p-16 flex flex-col md:flex-row items-center justify-between gap-8">
          {/* Decorative blobs */}

          <div className="relative z-10 max-w-xl text-center md:text-left">
            <span className="bg-white/10 text-white text-[11px] font-bold tracking-widest uppercase px-3 py-1 rounded-full border border-white/20">
              Ưu đãi đặc biệt hè 2026
            </span>
            <h2 className="text-3xl md:text-4xl font-bold tracking-tight mt-4 mb-4">
              Nhận ngay giảm giá 15%
              <br />
              cho lần đặt tour đầu tiên!
            </h2>
            <p className="text-blue-100 text-sm md:text-base font-light">
              Nhập mã <strong className="font-bold text-white">WELCOME15</strong> khi
              đặt tour để được giảm 15% (tối đa 1.000.000đ cho đơn từ 1.000.000đ).
            </p>
          </div>

          <div className="relative z-10 flex flex-col sm:flex-row gap-3 w-full md:w-auto justify-center shrink-0">
            <Link
              to="/register"
              className="bg-white hover:bg-blue-50 text-primary-700 font-bold text-sm px-8 py-3.5 rounded-full text-center transition-all shadow-lg hover:scale-[1.02] active:scale-[0.98]"
            >
              Đăng ký thành viên
            </Link>
            <Link
              to="/tours"
              className="border border-white/30 hover:border-white text-white hover:bg-white/10 font-bold text-sm px-8 py-3.5 rounded-full text-center transition-all"
            >
              Xem các Tour hot
            </Link>
          </div>
        </div>
      </section>

      {/* ── 6. TESTIMONIALS (đánh giá thật) ─────────────────────────────────── */}
      {testimonials.length > 0 && (
        <section className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 mb-16">
          <SectionHeading
            title="Khách hàng nói gì về Vivu Booking"
            subtitle="Những chia sẻ thực tế từ các lữ khách sau chuyến đi đầy cảm xúc"
          />
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {testimonials.map((t, idx) => (
              <div
                key={idx}
                className="bg-white rounded-xl p-8 border border-gray-100 shadow-sm hover:shadow-lg transition-all duration-300"
              >
                <div className="flex text-amber-400 gap-0.5 mb-4">
                  {Array.from({ length: t.stars }).map((_, i) => (
                    <svg
                      key={i}
                      className="w-4 h-4 fill-current"
                      viewBox="0 0 20 20"
                    >
                      <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                  ))}
                </div>
                <p className="text-gray-600 text-sm italic leading-relaxed mb-6">
                  "{t.comment}"
                </p>
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center font-bold">
                    {t.name.charAt(0).toUpperCase()}
                  </div>
                  <div>
                    <h4 className="font-bold text-gray-900 text-sm">{t.name}</h4>
                    <p className="text-gray-400 text-xs">{t.role}</p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </section>
      )}

      {/* ── 7. NEWSLETTER ───────────────────────────────────────────────────── */}
      <section className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
        <div className="bg-white border border-gray-200/60 rounded-xl p-8 md:p-12 shadow-sm text-center max-w-3xl mx-auto flex flex-col items-center">
          <div className="bg-primary-50 p-4 rounded-lg mb-4 text-primary-600 shadow-sm">
            <EnvelopeIcon className="w-8 h-8" />
          </div>
          <h2 className="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight mt-1">
            Đăng ký nhận cẩm nang du lịch
          </h2>
          <p className="text-gray-500 text-sm max-w-md mx-auto mt-2 mb-8 leading-relaxed">
            Chúng tôi sẽ gửi những kinh nghiệm du lịch bổ ích và thông tin
            khuyến mãi hấp dẫn mỗi tuần. Không spam, hủy bất kỳ lúc nào!
          </p>
          {newsletterDone ? (
            <div className="max-w-md mx-auto w-full p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg text-sm font-semibold">
              Đăng ký nhận bản tin thành công. Cảm ơn bạn!
            </div>
          ) : (
            <form
              onSubmit={handleNewsletterSubmit}
              className="flex flex-col sm:flex-row gap-3 max-w-md mx-auto w-full"
            >
              <input
                type="email"
                required
                value={newsletterEmail}
                onChange={(e) => setNewsletterEmail(e.target.value)}
                placeholder="Nhập địa chỉ email của bạn..."
                className="bg-gray-50 border border-gray-200 px-5 py-3 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:bg-white w-full text-gray-800 placeholder-gray-400 font-medium"
              />
              <button
                type="submit"
                disabled={newsletterSubmitting}
                className="bg-primary-600 hover:bg-primary-700 text-white font-bold text-sm px-6 py-3 rounded-lg whitespace-nowrap shadow-md transition-all cursor-pointer disabled:opacity-50"
              >
                {newsletterSubmitting ? "Đang đăng ký..." : "Đăng ký ngay"}
              </button>
            </form>
          )}
        </div>
      </section>
    </div>
  );
};

export default Home;
