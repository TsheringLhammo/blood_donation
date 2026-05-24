import React from "react";
import { HashRouter as Router, Routes, Route, Navigate } from "react-router-dom";
import { ToastContainer } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import Home from "./pages/Home";
import DonorRegister from "./pages/DonorRegister";
import RegistrationSuccess from "./pages/RegistrationSuccess";
import EligibilityDetails from "./pages/EligibilityDetails";
import Blooddonationcamp from "./pages/Blooddonationcamp";
import Aboutus from "./pages/Aboutus";
import Aboutblood from "./pages/Aboutblood";
import Donatingblood from "./pages/Donatingblood";
import Login from "./pages/Login";
import BloodBanks from "./pages/BloodBankList";
import PublicInformation from "./pages/PublicInformation";
import Dashboard from "./pages/Dashboard";
import DonorProfile from "./pages/DonorProfile";
import AdminDashboard from "./pages/AdminDashboard";
import AdminProfile from "./pages/AdminProfile";
import DoctorDashboard from "./pages/DoctorDashboard";
import DoctorProfile from "./pages/DoctorProfile";
import StaffDashboard from "./pages/StaffDashboard";
import StaffProfile from "./pages/StaffProfile";
import DonorsManagement from "./pages/admin/DonorsManagement";
import { isUserAuthorized } from "./utils/auth";

function ProtectedRoute({ roles, children }) {
  if (!isUserAuthorized(roles)) {
    return <Navigate to="/login" replace />;
  }
  return children;
}

function App() {
  return (
    <Router>
      <ToastContainer
        position="top-right"
        autoClose={5000}
        hideProgressBar={false}
        newestOnTop={true}
        closeOnClick
        rtl={false}
        pauseOnFocusLoss
        draggable
        pauseOnHover
        theme="colored"
      />
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/register" element={<DonorRegister />} />
        <Route path="/registration-success" element={<RegistrationSuccess />} />
        <Route path="/eligibility" element={<EligibilityDetails />} />
        <Route path="/camp" element={<Blooddonationcamp />} />
        <Route path="/about" element={<Aboutus />} />
        <Route path="/about-blood" element={<Aboutblood />} />
        <Route path="/public-information" element={<PublicInformation />} />
        <Route path="/donating-blood" element={<Donatingblood />} />
        <Route path="/login" element={<Login />} />
        <Route path="/blood-banks" element={<BloodBanks />} />
        <Route path="/dashboard" element={<ProtectedRoute roles={["donor"]}><Dashboard /></ProtectedRoute>} />
        <Route path="/profile" element={<ProtectedRoute roles={["donor"]}><DonorProfile /></ProtectedRoute>} />
        <Route path="/admin" element={<ProtectedRoute roles={["admin"]}><AdminDashboard /></ProtectedRoute>} />
        <Route path="/admin/profile" element={<ProtectedRoute roles={["admin"]}><AdminProfile /></ProtectedRoute>} />
        <Route path="/admin/donors" element={<ProtectedRoute roles={["admin"]}><DonorsManagement /></ProtectedRoute>} />
        <Route path="/doctor" element={<ProtectedRoute roles={["doctor"]}><DoctorDashboard /></ProtectedRoute>} />
        <Route path="/doctor/profile" element={<ProtectedRoute roles={["doctor"]}><DoctorProfile /></ProtectedRoute>} />
        <Route path="/staff" element={<ProtectedRoute roles={["staff", "admin"]}><StaffDashboard /></ProtectedRoute>} />
        <Route path="/staff/profile" element={<ProtectedRoute roles={["staff", "admin"]}><StaffProfile /></ProtectedRoute>} />
      </Routes>
    </Router>
  );
}

export default App;