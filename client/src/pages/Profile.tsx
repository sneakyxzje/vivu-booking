import React, { useState, useEffect } from "react";
import { useSearchParams, useNavigate } from "react-router-dom";
import { useAuth } from "@/hooks/useAuth";
import { MyBookingsTab } from "@/components/profile/MyBookingsTab";
import {
  User,
  Ticket,
  ShieldCheck,
  LogOut,
  Mail,
  Edit3,
  CheckCircle2,
  ChevronRight,
  Phone,
  Star,
  Sparkles
} from "lucide-react";

export const Profile: React.FC = () => {
  const { user, logout, updateUser } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();

  const currentTabParam = searchParams.get("tab") || "bookings";
  const [activeTab, setActiveTab] = useState<string>(currentTabParam);

  useEffect(() => {
    const tab = searchParams.get("tab");
    if (tab) {
      setActiveTab(tab);
    }
  }, [searchParams]);

  const handleTabChange = (tabKey: string) => {
    setActiveTab(tabKey);
    setSearchParams({ tab: tabKey });
  };

  // Profile Edit State
  const [profileName, setProfileName] = useState(user?.name || "");
  const [profilePhone, setProfilePhone] = useState(user?.phone || "");
  const [saveSuccess, setSaveSuccess] = useState(false);

  const handleUpdateProfile = (e: React.FormEvent) => {
    e.preventDefault();
    if (user) {
      updateUser({
        ...user,
        name: profileName,
        phone: profilePhone,
      });
      setSaveSuccess(true);
      setTimeout(() => setSaveSuccess(false), 3000);
    }
  };

  const handleLogout = () => {
    logout();
    navigate("/");
  };

  return (
    <div className="min-h-screen bg-gray-50/60 pt-[40px] pb-10">
      <div className="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8">

        {/* LIGHT, ELEGANT PROFILE HEADER CARD */}
        <div className="bg-white rounded-3xl p-5 sm:p-6 border border-gray-200/80 shadow-sm mb-5">
          <div className="flex flex-col md:flex-row items-center justify-between gap-6">

            {/* User Avatar & Info */}
            <div className="flex flex-col sm:flex-row items-center gap-5 text-center sm:text-left">
              <div className="relative">
                <div className="w-20 h-20 sm:w-22 sm:h-22 rounded-full bg-primary-50 text-primary-600 border-2 border-primary-200 flex items-center justify-center text-3xl font-bold tracking-wider shadow-sm">
                  {user?.name?.charAt(0).toUpperCase() || "U"}
                </div>
                <button
                  onClick={() => handleTabChange("info")}
                  className="absolute bottom-0 right-0 p-1.5 bg-primary-600 text-white rounded-full shadow hover:bg-primary-700 transition-colors"
                  title="Chỉnh sửa thông tin"
                >
                  <Edit3 className="w-3.5 h-3.5" />
                </button>
              </div>
              <div>
                <div className="flex items-center justify-center sm:justify-start gap-2">
                  <h1 className="text-2xl font-bold text-gray-900 tracking-tight">
                    {user?.name || "Khách hàng Vivu"}
                  </h1>
                  <span className="bg-emerald-50 text-emerald-700 text-[11px] font-semibold px-2.5 py-0.5 rounded-full border border-emerald-200">
                    Tài khoản xác thực
                  </span>
                </div>
                <p className="text-gray-500 text-xs mt-1 flex items-center justify-center sm:justify-start gap-1.5">
                  <Mail className="w-3.5 h-3.5 text-gray-400" /> {user?.email || "chuacapnhat@email.com"}
                </p>

                <div className="flex items-center gap-2 mt-3 justify-center sm:justify-start">
                  <span className="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-xs font-medium border border-gray-200">
                    Khách hàng thân thiết
                  </span>
                  <span className="bg-amber-50 text-amber-700 px-3 py-1 rounded-full text-xs font-semibold border border-amber-200 flex items-center gap-1">
                    <Star className="w-3.5 h-3.5 fill-amber-400 text-amber-400" /> Thành viên Vivu
                  </span>
                </div>
              </div>
            </div>

            {/* Clean Stats Cards */}
            <div className="flex items-center gap-3 bg-gray-50 p-2.5 rounded-2xl border border-gray-200/60 w-full md:w-auto justify-around">
              <div className="text-center px-5 py-2">
                <span className="block text-xl font-bold text-gray-900">Tour</span>
                <span className="text-xs text-gray-500 font-medium">Đã đăng ký</span>
              </div>
              <div className="w-px h-8 bg-gray-200" />
              <div className="text-center px-5 py-2">
                <span className="block text-xl font-bold text-primary-600">Vivu</span>
                <span className="text-xs text-gray-500 font-medium">Booking Safe</span>
              </div>
            </div>

          </div>
        </div>

        {/* MAIN LAYOUT */}
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">

          {/* SIDEBAR NAVIGATION */}
          <div className="lg:col-span-1">
            <div className="bg-white rounded-2xl p-3 shadow-sm border border-gray-200/80 sticky top-28 space-y-1">
              <button
                onClick={() => handleTabChange("bookings")}
                className={`w-full flex items-center justify-between px-4 py-3.5 rounded-xl font-medium text-xs sm:text-sm transition-all ${activeTab === "bookings"
                  ? "bg-primary-50 text-primary-600 font-bold shadow-sm border border-primary-100"
                  : "text-gray-600 hover:bg-gray-50 hover:text-gray-900"
                  }`}
              >
                <div className="flex items-center gap-3">
                  <Ticket className={`w-4 h-4 ${activeTab === "bookings" ? "text-primary-600" : "text-gray-400"}`} />
                  Quản lý tour đã đặt
                </div>
                <ChevronRight className={`w-4 h-4 ${activeTab === "bookings" ? "text-primary-600" : "text-gray-300"}`} />
              </button>

              <button
                onClick={() => handleTabChange("info")}
                className={`w-full flex items-center justify-between px-4 py-3.5 rounded-xl font-medium text-xs sm:text-sm transition-all ${activeTab === "info"
                  ? "bg-primary-50 text-primary-600 font-bold shadow-sm border border-primary-100"
                  : "text-gray-600 hover:bg-gray-50 hover:text-gray-900"
                  }`}
              >
                <div className="flex items-center gap-3">
                  <User className={`w-4 h-4 ${activeTab === "info" ? "text-primary-600" : "text-gray-400"}`} />
                  Thông tin cá nhân
                </div>
                <ChevronRight className={`w-4 h-4 ${activeTab === "info" ? "text-primary-600" : "text-gray-300"}`} />
              </button>

              <button
                onClick={() => handleTabChange("security")}
                className={`w-full flex items-center justify-between px-4 py-3.5 rounded-xl font-medium text-xs sm:text-sm transition-all ${activeTab === "security"
                  ? "bg-primary-50 text-primary-600 font-bold shadow-sm border border-primary-100"
                  : "text-gray-600 hover:bg-gray-50 hover:text-gray-900"
                  }`}
              >
                <div className="flex items-center gap-3">
                  <ShieldCheck className={`w-4 h-4 ${activeTab === "security" ? "text-primary-600" : "text-gray-400"}`} />
                  Đổi mật khẩu & Bảo mật
                </div>
                <ChevronRight className={`w-4 h-4 ${activeTab === "security" ? "text-primary-600" : "text-gray-300"}`} />
              </button>

              <div className="pt-2 border-t border-gray-100 mt-2">
                <button
                  onClick={handleLogout}
                  className="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl font-medium text-xs sm:text-sm text-rose-600 hover:bg-rose-50 transition-colors"
                >
                  <LogOut className="w-4 h-4" />
                  Đăng xuất tài khoản
                </button>
              </div>
            </div>
          </div>

          {/* MAIN TAB CONTENT */}
          <div className="lg:col-span-3">

            {/* TAB 1: BOOKINGS TAB COMPONENT */}
            {activeTab === "bookings" && <MyBookingsTab />}

            {/* TAB 2: THÔNG TIN CÁ NHÂN (INFO) */}
            {activeTab === "info" && (
              <div className="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-gray-200/80">
                <div className="border-b border-gray-100 pb-5 mb-6">
                  <h2 className="text-xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
                    <User className="w-5 h-5 text-primary-600" /> Hồ sơ thông tin cá nhân
                  </h2>
                  <p className="text-xs text-gray-500 mt-1">Cập nhật thông tin của bạn để thuận tiện khi đăng ký tour</p>
                </div>

                {saveSuccess && (
                  <div className="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold flex items-center gap-2">
                    <CheckCircle2 className="w-4 h-4 text-emerald-600" /> Cập nhật thông tin cá nhân thành công!
                  </div>
                )}

                <form onSubmit={handleUpdateProfile} className="space-y-6 max-w-xl">
                  <div className="space-y-2">
                    <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider">Họ và tên</label>
                    <div className="relative">
                      <User className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                      <input
                        type="text"
                        value={profileName}
                        onChange={(e) => setProfileName(e.target.value)}
                        className="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                        required
                      />
                    </div>
                  </div>

                  <div className="space-y-2">
                    <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider">Địa chỉ Email</label>
                    <div className="relative">
                      <Mail className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                      <input
                        type="email"
                        value={user?.email || ""}
                        disabled
                        className="w-full pl-10 pr-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-sm font-medium text-gray-500 cursor-not-allowed"
                      />
                    </div>
                    <span className="text-[11px] text-gray-400">Email cố định dùng để xác thực tài khoản.</span>
                  </div>

                  <div className="space-y-2">
                    <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider">Số điện thoại</label>
                    <div className="relative">
                      <Phone className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                      <input
                        type="text"
                        placeholder="Nhập số điện thoại..."
                        value={profilePhone}
                        onChange={(e) => setProfilePhone(e.target.value)}
                        className="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                      />
                    </div>
                  </div>

                  <button
                    type="submit"
                    className="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold text-xs rounded-xl shadow-sm transition-colors"
                  >
                    Lưu thay đổi thông tin
                  </button>
                </form>
              </div>
            )}

            {/* TAB 3: ĐỔI MẬT KHẨU (SECURITY) */}
            {activeTab === "security" && (
              <div className="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-gray-200/80">
                <div className="border-b border-gray-100 pb-5 mb-6">
                  <h2 className="text-xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
                    <ShieldCheck className="w-5 h-5 text-primary-600" /> Đổi mật khẩu & Bảo mật
                  </h2>
                  <p className="text-xs text-gray-500 mt-1">Đảm bảo an toàn cho tài khoản cá nhân của bạn</p>
                </div>

                <form className="space-y-5 max-w-xl" onSubmit={(e) => { e.preventDefault(); alert("Mật khẩu đã được cập nhật thành công!"); }}>
                  <div className="space-y-2">
                    <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider">Mật khẩu hiện tại</label>
                    <input
                      type="password"
                      placeholder="••••••••"
                      className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                      required
                    />
                  </div>

                  <div className="space-y-2">
                    <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider">Mật khẩu mới</label>
                    <input
                      type="password"
                      placeholder="••••••••"
                      className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                      required
                    />
                  </div>

                  <div className="space-y-2">
                    <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider">Xác nhận mật khẩu mới</label>
                    <input
                      type="password"
                      placeholder="••••••••"
                      className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                      required
                    />
                  </div>

                  <button
                    type="submit"
                    className="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold text-xs rounded-xl shadow-sm transition-colors"
                  >
                    Cập nhật mật khẩu mới
                  </button>
                </form>
              </div>
            )}
          </div>
        </div>

      </div>
    </div>
  );
};

export default Profile;
