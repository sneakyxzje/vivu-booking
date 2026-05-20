import React from "react";
import { Link } from "react-router-dom";

export const NotFound: React.FC = () => {
  return (
    <div style={{ textAlign: "center", padding: "80px 20px" }}>
      <h1 style={{ fontSize: "4rem", fontWeight: 700, color: "#4f46e5" }}>
        404
      </h1>
      <p
        style={{ fontSize: "1.25rem", color: "#6b7280", marginBottom: "24px" }}
      >
        Trang bạn tìm không tồn tại.
      </p>
      <Link
        to="/"
        style={{
          display: "inline-block",
          padding: "10px 24px",
          backgroundColor: "#4f46e5",
          color: "#fff",
          borderRadius: "8px",
          textDecoration: "none",
          fontSize: "0.875rem",
        }}
      >
        Về trang chủ
      </Link>
    </div>
  );
};

export default NotFound;
