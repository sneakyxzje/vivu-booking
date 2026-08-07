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
import ScheduleAttendance from "@/pages/admin/ScheduleAttendance";
import { GuideDashboard } from "@/pages/guide/GuideDashboard";
import { GuideTours } from "@/pages/guide/GuideTours";
import { GuideBookings } from "@/pages/guide/GuideBookings";
import { GuideAttendance } from "@/pages/guide/GuideAttendance";
import TourDetail from "@/components/TourDetail";
import { CreateTourForm } from "@/pages/admin/create/CreateTourForm";
import BookingTour from "@/pages/BookingTour";
import BookingSuccess from "@/pages/BookingSuccess";
import { Profile } from "@/pages/Profile";

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
            path: "/admin/bookings",
            element: <BookingManagement />,
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



