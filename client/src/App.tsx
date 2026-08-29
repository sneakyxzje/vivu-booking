import React from "react";
import {
  createBrowserRouter,
  Navigate,
  RouterProvider,
} from "react-router-dom";
import { ProtectedRoute } from "@/components/ProtectedRoute";
import { Layout } from "@/components/Layout";
import { GuideLayout } from "@/components/guide/GuideLayout";
import { AdminLayout } from "@/components/admin/AdminLayout";
import { Login } from "@/pages/Login";
import { Register } from "@/pages/Register";
import { NotFound } from "@/pages/NotFound";
import { Home } from "@/pages/Home";
import { Tours } from "@/pages/Tours";
import { PaymentResult } from "@/pages/PaymentResult";
import Dashboard from "@/pages/admin/Dashboard";
import TourList from "@/pages/admin/TourList";
import AdminTourDetail from "@/pages/admin/TourDetail";
import BookingManagement from "@/pages/admin/BookingManagement";
import GuideManagement from "@/pages/admin/GuideManagement";
import DiscountCodeManagement from "@/pages/admin/DiscountCodeManagement";
import ServiceManagement from "@/pages/admin/ServiceManagement";
import CategoryManagement from "@/pages/admin/CategoryManagement";
import ScheduleAttendance from "@/pages/admin/ScheduleAttendance";
import AttendanceReport from "@/pages/admin/AttendanceReport";
import ScheduleManagement from "@/pages/admin/ScheduleManagement";
import AuditLogManagement from "@/pages/admin/AuditLogManagement";
import ChangeRequestManagement from "@/pages/admin/ChangeRequestManagement";
import CancellationPolicyManagement from "@/pages/admin/CancellationPolicyManagement";
import { GuideDashboard } from "@/pages/guide/GuideDashboard";
import GuideAssignments from "@/pages/guide/GuideAssignments";
import { GuideTours } from "@/pages/guide/GuideTours";
import { GuideBookings } from "@/pages/guide/GuideBookings";
import { GuideAttendance } from "@/pages/guide/GuideAttendance";
import GuideIncidents from "@/pages/guide/GuideIncidents";
import GuideHandovers from "@/pages/guide/GuideHandovers";
import IncidentManagement from "@/pages/admin/IncidentManagement";
import HandoverManagement from "@/pages/admin/HandoverManagement";
import NotificationCenter from "@/pages/NotificationCenter";
import PolicyPage from "@/pages/PolicyPage";
import TourDetail from "@/components/TourDetail";
import { CreateTourForm } from "@/pages/admin/create/CreateTourForm";
import BookingTour from "@/pages/BookingTour";
import BookingSuccess from "@/pages/BookingSuccess";
import { Profile } from "@/pages/Profile";
import InfoPage from "@/pages/InfoPage";
import { BookingLookup } from "@/pages/BookingLookup";
import GroupBooking from "@/pages/GroupBooking";
import PassengerDeclaration from "@/pages/PassengerDeclaration";
import GroupBookingManagement from "@/pages/admin/GroupBookingManagement";

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
        path: "/tours/:id",
        element: <TourDetail />,
      },
      {
        path: "/tours/:id/booking",
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
        path: "/contact",
        element: <InfoPage />,
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
        element: <AdminLayout />,
        children: [
          {
            path: "/admin",
            element: <Navigate to="/admin/dashboard" replace />,
          },
          {
            path: "/admin/dashboard",
            element: <Dashboard />,
          },
          {
            path: "/admin/tours",
            element: <TourList />,
          },
          {
            path: "/admin/tours/create",
            element: <CreateTourForm />,
          },
          {
            path: "/admin/tours/:id/edit",
            element: <CreateTourForm />,
          },
          {
            path: "/admin/tours/:id",
            element: <AdminTourDetail />,
          },
          {
            path: "/admin/tour-schedules/:scheduleId/attendance",
            element: <ScheduleAttendance />,
          },
          {
            path: "/admin/attendance-reports",
            element: <AttendanceReport />,
          },
          {
            path: "/admin/schedules",
            element: <ScheduleManagement />,
          },
          {
            path: "/admin/notifications",
            element: <NotificationCenter />,
          },
          {
            path: "/admin/audit-logs",
            element: <AuditLogManagement />,
          },
          {
            path: "/admin/incidents",
            element: <IncidentManagement />,
          },
          {
            path: "/admin/handovers",
            element: <HandoverManagement />,
          },
          {
            path: "/admin/change-requests",
            element: <ChangeRequestManagement />,
          },
          {
            path: "/admin/cancellation-policies",
            element: <CancellationPolicyManagement />,
          },
          {
            path: "/admin/bookings",
            element: <BookingManagement />,
          },
          {
            path: "/admin/group-bookings",
            element: <GroupBookingManagement />,
          },
          {
            path: "/admin/discount-codes",
            element: <DiscountCodeManagement />,
          },
          {
            path: "/admin/guides",
            element: <GuideManagement />,
          },
          {
            path: "/admin/services",
            element: <ServiceManagement />,
          },
          {
            path: "/admin/categories",
            element: <CategoryManagement />,
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



