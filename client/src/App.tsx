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
import Dashboard from "@/pages/admin/Dashboard";
import TourList from "@/pages/admin/TourList";
import { GuideDashboard } from "@/pages/guide/GuideDashboard";
import { GuideTours } from "@/pages/guide/GuideTours";
import { GuideBookings } from "@/pages/guide/GuideBookings";
import { GuideTourForm } from "@/pages/guide/GuideTourForm";
import TourDetail from "@/components/TourDetail";

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
        path: "/flights",
        element: <div>Vé máy bay — TODO</div>,
      },
      {
        path: "/hotels",
        element: <div>Khách sạn — TODO</div>,
      },
      {
        path: "/combos",
        element: <div>Combo du lịch — TODO</div>,
      },
      {
        path: "/services",
        element: <div>Dịch vụ cộng thêm — TODO</div>,
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
            element: <div>Profile — TODO</div>,
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
            path: "/guide/tours/create",
            element: <GuideTourForm />,
          },
          {
            path: "/guide/tours/:id/edit",
            element: <GuideTourForm />,
          },
          {
            path: "/guide/bookings",
            element: <GuideBookings />,
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












