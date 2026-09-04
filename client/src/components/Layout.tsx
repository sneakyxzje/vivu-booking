import React from "react";
import { Outlet } from "react-router-dom";
import { Header } from "@/components/Header";
import { Footer } from "@/components/Footer";
import { TourChatWidget } from "@/components/chat/TourChatWidget";

export const Layout: React.FC = () => {
  return (
    <div className="min-h-screen flex flex-col">
      <Header />
      <main className="flex-1 pt-16">
        <Outlet />
      </main>
      <Footer />
      {/* Chỉ ở khung khách, không có ở khung điều hành và hướng dẫn viên. */}
      <TourChatWidget />
    </div>
  );
};

export default Layout;
