import { Skeleton } from "antd";
import React from "react";
import { Outlet } from "react-router-dom";
import { Header } from "@/components/Header";
import { Footer } from "@/components/Footer";
import { TourChatWidget } from "@/components/chat/TourChatWidget";
import { CustomerUIProvider } from "@/components/CustomerUIProvider";

export const Layout: React.FC = () => {
  return (
    <CustomerUIProvider><div className="min-h-screen flex flex-col">
      <Header />
      <main className="flex-1 pt-16">
        <React.Suspense fallback={<div className="mx-auto max-w-6xl px-4 py-10"><Skeleton active paragraph={{ rows: 8 }} /></div>}>
          <Outlet />
        </React.Suspense>
      </main>
      <Footer />
      {/* Chỉ ở khung khách, không có ở khung điều hành và hướng dẫn viên. */}
      <TourChatWidget />
    </div></CustomerUIProvider>
  );
};

export default Layout;
