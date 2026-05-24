import React from "react";
import { createBrowserRouter, RouterProvider } from "react-router-dom";
import { ProtectedRoute } from "@/components/ProtectedRoute";
import { Layout } from "@/components/Layout";
import { Login } from "@/pages/Login";
import { Register } from "@/pages/Register";
import { NotFound } from "@/pages/NotFound";

const router = createBrowserRouter([
  // ── LAYOUT (Header + Footer) ─────────────────────────────────────────────
  {
    element: <Layout />,
    children: [
      {
        path: "/",
        element: <div>Home</div>,
      },
      {
        path: "/tours",
        element: <div>Tour trọn gói — TODO</div>,
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
      {
        element: <ProtectedRoute allowedRoles={["admin"]} />,
        children: [
          {
            path: "/admin/dashboard",
            element: <div>Admin Dashboard — TODO</div>,
          },
        ],
      },
    ],
  },

  // ── 404 ──────────────────────────────────────────────────────────────────
  {
    path: "*",
    element: <NotFound />,
  },
]);

export const App: React.FC = () => <RouterProvider router={router} />;

export default App;
