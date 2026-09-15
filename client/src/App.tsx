import React from "react";
import {
  createBrowserRouter,
  Navigate,
  RouterProvider,
} from "react-router-dom";
import { ProtectedRoute } from "@/components/ProtectedRoute";
import { Layout } from "@/components/Layout";
import { GuideLayout } from "@/components/guide/GuideLayout";
import { Login } from "@/pages/Login";
import { Register } from "@/pages/Register";
import { ForgotPassword } from "@/pages/ForgotPassword";
import { ResetPassword } from "@/pages/ResetPassword";
import { NotFound } from "@/pages/NotFound";
import { Home } from "@/pages/Home";
import { Tours } from "@/pages/Tours";
import { PaymentResult } from "@/pages/PaymentResult";
import { GuideDashboard } from "@/pages/guide/GuideDashboard";
import GuideAssignments from "@/pages/guide/GuideAssignments";
import { GuideTours } from "@/pages/guide/GuideTours";
import { GuideBookings } from "@/pages/guide/GuideBookings";
import { GuideAttendance } from "@/pages/guide/GuideAttendance";
import GuideIncidents from "@/pages/guide/GuideIncidents";
import GuideHandovers from "@/pages/guide/GuideHandovers";
import NotificationCenter from "@/pages/NotificationCenter";
import PolicyPage from "@/pages/PolicyPage";
import TourDetail from "@/components/TourDetail";
import BookingTour from "@/pages/BookingTour";
import BookingSuccess from "@/pages/BookingSuccess";
import { Profile } from "@/pages/Profile";
import InfoPage from "@/pages/InfoPage";
import ContactPage from "@/pages/ContactPage";
import { BookingLookup } from "@/pages/BookingLookup";
import GroupBooking from "@/pages/GroupBooking";
import PassengerDeclaration from "@/pages/PassengerDeclaration";

const router = createBrowserRouter([
  // 1. NHÓM ROUTES CHO USER (Sử dụng Layout chung của User có Header/Footer)
  {
    element: <Layout />,
    children: [
      {
        path: "/",
        element: <Home />,
      },
      {
        path: "/tours",
        element: <Tours />,
      },
      {
        /*
         * Tham số là SLUG, không phải id — `/tours/tour-ha-long-3n2d` chứ không `/tours/17`.
         *
         * Địa chỉ đọc được là thứ người ta dán cho nhau và là thứ máy tìm kiếm xếp hạng; một con
         * số không nói gì về nội dung trang. Máy chủ vẫn nhận cả id (xem TourController::show),
         * nên mọi liên kết dạng số đã gửi đi trước đây không gãy.
         */
        path: "/tours/:slug",
        element: <TourDetail />,
      },
      {
        path: "/tours/:slug/booking",
        element: <BookingTour />,
      },
      {
        path: "/booking-success/:id",
        element: <BookingSuccess />,
      },
      {
        path: "/payment-result",
        element: <PaymentResult />,
      },
      {
        path: "/booking-lookup",
        element: <BookingLookup />,
      },
      {
        path: "/group-booking",
        element: <GroupBooking />,
      },
      {
        // G03 - Khai danh sách hành khách sau khi đặt. Mở bằng mã tra cứu, không cần đăng nhập.
        path: "/bookings/:publicToken/passengers",
        element: <PassengerDeclaration />,
      },
      {
        path: "/about",
        element: <InfoPage />,
      },
      /*
       * Hai đường dẫn cũ chuyển hướng chứ không xóa hẳn.
       *
       * Chúng đã nằm trong thư gửi khách và trong ô đồng ý điều khoản ở trang đăng ký. Xóa thì
       * người bấm vào rơi vào trang 404 và mất niềm tin đúng lúc họ đang định đăng ký.
       *
       * `replace` để nút Quay lại của trình duyệt không kẹt giữa hai lần chuyển hướng.
       */
      {
        path: "/terms",
        element: <Navigate to="/chinh-sach#dieu-khoan" replace />,
      },
      {
        path: "/privacy",
        element: <Navigate to="/chinh-sach#bao-mat" replace />,
      },
      {
        // Trước đây trỏ vào `InfoPage` — cùng một trang chữ tĩnh với `/about`, không ô nào để gõ.
        path: "/contact",
        element: <ContactPage />,
      },
      {
        /*
         * Chính sách hủy, đổi, hoàn tiền — trang riêng, không nằm chung với `InfoPage`.
         *
         * Ba trang kia là chữ tĩnh; trang này đọc bảng phí thật từ máy chủ và đổi theo mỗi lần
         * điều hành sửa. Nhét chung thì một trang tĩnh lại phải biết gọi API.
         */
        path: "/chinh-sach",
        element: <PolicyPage />,
      },
      {
        path: "/login",
        element: <Login />,
      },
      {
        path: "/register",
        element: <Register />,
      },
      {
        // Trang đăng nhập vẫn trỏ tới đây từ trước khi có màn hình này — liên kết cũ rơi vào 404.
        path: "/forgot-password",
        element: <ForgotPassword />,
      },
      {
        // Địa chỉ này nằm trong thư gửi khách, kèm ?token=...&email=... — đổi đường dẫn thì mọi
        // liên kết đã gửi đi đều hỏng. Xem App\Mail\PasswordResetMail.
        path: "/reset-password",
        element: <ResetPassword />,
      },
      {
        element: <ProtectedRoute />,
        children: [
          {
            path: "/profile",
            element: <Profile />,
          },
          {
            path: "/my-bookings",
            element: <Profile />,
          },
        ],
      },
    ],
  },

  // 2. NHÓM ROUTES CHO GUIDE (Sử dụng GuideLayout riêng biệt)
  {
    element: <ProtectedRoute allowedRoles={["guide"]} />,
    children: [
      {
        element: <GuideLayout />,
        children: [
          {
            path: "/guide",
            element: <Navigate to="/guide/dashboard" replace />,
          },
          {
            path: "/guide/dashboard",
            element: <GuideDashboard />,
          },
          {
            path: "/guide/assignments",
            element: <GuideAssignments />,
          },
          {
            path: "/guide/tours",
            element: <GuideTours />,
          },
          {
            path: "/guide/bookings",
            element: <GuideBookings />,
          },
          {
            path: "/guide/attendance/:scheduleId",
            element: <GuideAttendance />,
          },
          {
            path: "/guide/incidents",
            element: <GuideIncidents />,
          },
          {
            path: "/guide/handovers",
            element: <GuideHandovers />,
          },
          {
            // Cùng một màn hình với `/admin/notifications`: nội dung do máy chủ lọc theo người
            // đăng nhập, nên chỉ khác đường dẫn và khung bao ngoài.
            path: "/guide/notifications",
            element: <NotificationCenter />,
          },
        ],
      },
    ],
  },

  // 3. NHÓM ROUTES CHO ADMIN (Sử dụng AdminLayout riêng biệt)
  {
    element: <ProtectedRoute allowedRoles={["admin"]} />,
    children: [
      {
        lazy: async () => ({ Component: (await import("@/components/admin/AdminLayout")).AdminLayout }),
        children: [
          {
            path: "/admin",
            element: <Navigate to="/admin/dashboard" replace />,
          },
          {
            path: "/admin/dashboard",
            lazy: async () => ({ Component: (await import("@/pages/admin/Dashboard")).default }),
          },
          {
            path: "/admin/tours",
            lazy: async () => ({ Component: (await import("@/pages/admin/TourList")).default }),
          },
          {
            path: "/admin/tours/create",
            lazy: async () => ({ Component: (await import("@/pages/admin/create/CreateTourForm")).CreateTourForm }),
          },
          {
            path: "/admin/tours/:id/edit",
            lazy: async () => ({ Component: (await import("@/pages/admin/create/CreateTourForm")).CreateTourForm }),
          },
          {
            path: "/admin/tours/:id",
            lazy: async () => ({ Component: (await import("@/pages/admin/TourDetail")).default }),
          },
          {
            path: "/admin/tour-schedules/:scheduleId/attendance",
            lazy: async () => ({ Component: (await import("@/pages/admin/ScheduleAttendance")).default }),
          },
          {
            path: "/admin/attendance-reports",
            lazy: async () => ({ Component: (await import("@/pages/admin/AttendanceReport")).default }),
          },
          {
            path: "/admin/schedules",
            lazy: async () => ({ Component: (await import("@/pages/admin/ScheduleManagement")).default }),
          },
          {
            path: "/admin/notifications",
            lazy: async () => ({ Component: (await import("@/pages/admin/AdminNotifications")).default }),
          },
          {
            path: "/admin/audit-logs",
            lazy: async () => ({ Component: (await import("@/pages/admin/AuditLogManagement")).default }),
          },
          {
            path: "/admin/sandbox",
            lazy: async () => ({ Component: (await import("@/pages/admin/SandboxLab")).default }),
          },
          {
            path: "/admin/incidents",
            lazy: async () => ({ Component: (await import("@/pages/admin/IncidentManagement")).default }),
          },
          {
            path: "/admin/handovers",
            lazy: async () => ({ Component: (await import("@/pages/admin/HandoverManagement")).default }),
          },
          {
            path: "/admin/change-requests",
            lazy: async () => ({ Component: (await import("@/pages/admin/ChangeRequestManagement")).default }),
          },
          {
            path: "/admin/bookings",
            lazy: async () => ({ Component: (await import("@/pages/admin/BookingManagement")).default }),
          },
          {
            path: "/admin/group-bookings",
            lazy: async () => ({ Component: (await import("@/pages/admin/GroupBookingManagement")).default }),
          },
          {
            path: "/admin/discount-codes",
            lazy: async () => ({ Component: (await import("@/pages/admin/DiscountCodeManagement")).default }),
          },
          {
            path: "/admin/guides",
            lazy: async () => ({ Component: (await import("@/pages/admin/GuideManagement")).default }),
          },
          {
            path: "/admin/services",
            lazy: async () => ({ Component: (await import("@/pages/admin/ServiceManagement")).default }),
          },
          {
            path: "/admin/categories",
            lazy: async () => ({ Component: (await import("@/pages/admin/CategoryManagement")).default }),
          },
          {
            path: "/admin/reviews",
            lazy: async () => ({ Component: (await import("@/pages/admin/ReviewManagement")).default }),
          },
          {
            path: "/admin/users",
            lazy: async () => ({ Component: (await import("@/pages/admin/UserManagement")).default }),
          },
          {
            // Một cửa cho mọi câu hỏi về tiền: sổ, phải thu, phải trả. Xem FinanceHub.
            path: "/admin/transactions",
            lazy: async () => ({ Component: (await import("@/pages/admin/FinanceHub")).default }),
          },
          {
            // Hai đường cũ vẫn sống, chỉ dẫn sang đúng tab: liên kết đã gửi cho kế toán và các
            // trang đang trỏ tới chúng không được gãy vì một lần sắp lại menu.
            path: "/admin/refunds",
            element: <Navigate to="/admin/transactions?tab=refunds" replace />,
          },
          {
            path: "/admin/receivables",
            element: <Navigate to="/admin/transactions?tab=receivables" replace />,
          },
          {
            path: "/admin/contact-messages",
            lazy: async () => ({ Component: (await import("@/pages/admin/ContactMessages")).default }),
          },
        ],
      },
    ],
  },

  // 4. TRANG 404
  {
    path: "*",
    element: <NotFound />,
  },
]);

export const App: React.FC = () => <RouterProvider router={router} />;

export default App;



