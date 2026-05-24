import React from "react";
import { createBrowserRouter, RouterProvider } from "react-router-dom";
import { ProtectedRoute } from "@/components/ProtectedRoute";
import { NotFound } from "@/pages/NotFound";

const router = createBrowserRouter([
  // ── PUBLIC ROUTES ────────────────────────────────────────────────────────
  {
    path: "/",
    element: <div>Home</div>,
  },
  {
    path: "/login",
    element: <div>Login </div>,
  },
  {
    path: "/register",
    element: <div>Register</div>,
  },

  // User nào đã đăng nhập thì có thể access
  {
    element: <ProtectedRoute />,
    children: [
      {
        path: "/profile",
        element: <div>Profile — TODO</div>,
      },
    ],
  },

  // Chỉ host access
  {
    element: <ProtectedRoute allowedRoles={["host"]} />,
    children: [
      {
        path: "/host/dashboard",
        element: <div>Host Dashboard — TODO</div>,
      },
    ],
  },

  // Chỉ admin access
  {
    element: <ProtectedRoute allowedRoles={["admin"]} />,
    children: [
      {
        path: "/admin/dashboard",
        element: <div>Admin Dashboard — TODO</div>,
      },
    ],
  },

  {
    path: "*",
    element: <NotFound />,
  },
]);

export const App: React.FC = () => <RouterProvider router={router} />;

export default App;
