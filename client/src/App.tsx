import React from "react";
import { createBrowserRouter, RouterProvider } from "react-router-dom";
import { ProtectedRoute } from "@/components/ProtectedRoute";
import { Layout } from "@/components/Layout";
import { Login } from "@/pages/Login";
import { Register } from "@/pages/Register";
import { NotFound } from "@/pages/NotFound";
import { Home } from "@/pages/Home";
import { Tours } from "@/pages/Tours";
import Dashboard from "@/pages/admin/Dashboard";
import TourList from "@/pages/admin/TourList";


const router = createBrowserRouter([
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
      // User đã đăng nhập
      {
        element: <ProtectedRoute />,
        children: [
          {
            path: "/profile",
            element: <div>Profile — TODO</div>,
          },
        ],
      },
      // Chỉ host
      {
        element: <ProtectedRoute allowedRoles={["host"]} />,
        children: [
          {
            path: "/host/dashboard",
            element: <div>Host Dashboard — TODO</div>,
          },
        ],
      },
      // Chỉ admin
      // {
      //   element: <ProtectedRoute allowedRoles={["admin"]} />,
      //   children: [
      //   {
      //     path: "/admin/dashboard",
      //     element: <Dashboard />,
      //   }
      //   ],
      // },
      
    ],

    
  },
  // admin
  {
    path: "/admin/dashboard",
    element: <Dashboard />,
  },
  {
      path: "/admin/tours",
      element: <TourList />,
  },
  // ── 404 ──────────────────────────────────────────────────────────────────
  {
    path: "*",
    element: <NotFound />,
  },
]);

export const App: React.FC = () => <RouterProvider router={router} />;

export default App;
