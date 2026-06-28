import React from "react";
import {
  createBrowserRouter,
  Navigate,
  RouterProvider,
} from "react-router-dom";
import { ProtectedRoute } from "@/components/ProtectedRoute";
import { Layout } from "@/components/Layout";
import { HostLayout } from "@/components/host/HostLayout";
import { AdminLayout } from "@/components/admin/AdminLayout";
import { Login } from "@/pages/Login";
import { Register } from "@/pages/Register";
import { NotFound } from "@/pages/NotFound";
import { Home } from "@/pages/Home";
import { Tours } from "@/pages/Tours";
import Dashboard from "@/pages/admin/Dashboard";
import TourList from "@/pages/admin/TourList";
import { HostDashboard } from "@/pages/host/HostDashboard";
import { HostTours } from "@/pages/host/HostTours";
import { HostBookings } from "@/pages/host/HostBookings";
import { HostTourForm } from "@/pages/host/HostTourForm";
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

  // 2. NHÓM ROUTES CHO HOST (Sử dụng HostLayout riêng biệt)
  {
    element: <ProtectedRoute allowedRoles={["host"]} />,
    children: [
      {
        element: <HostLayout />,
        children: [
          {
            path: "/host",
            element: <Navigate to="/host/dashboard" replace />,
          },
          {
            path: "/host/dashboard",
            element: <HostDashboard />,
          },
          {
            path: "/host/tours",
            element: <HostTours />,
          },
          {
            path: "/host/tours/create",
            element: <HostTourForm />,
          },
          {
            path: "/host/tours/:id/edit",
            element: <HostTourForm />,
          },
          {
            path: "/host/bookings",
            element: <HostBookings />,
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

