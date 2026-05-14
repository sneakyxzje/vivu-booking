import React, { useState } from "react";
import api from "../services/api";

export const Home: React.FC = () => {
  const [response, setResponse] = useState<string>("");
  const [loading, setLoading] = useState<boolean>(false);

  const testEndpoint = async () => {
    setLoading(true);
    try {
      const res = await api.get("/test");
      setResponse(JSON.stringify(res.data, null, 2));
    } catch (err: any) {
      setResponse(`Error calling server: ${err.message}`);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <h1>Vivu Booking Base - Client</h1>
      <button onClick={testEndpoint} disabled={loading}>
        {loading ? "Testing..." : "Test Server Endpoint"}
      </button>

      {response && <pre>{response}</pre>}
    </div>
  );
};
