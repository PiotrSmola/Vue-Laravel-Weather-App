// Zapytania API zarządzania kontem (profil, hasło, sesje, eksport, usunięcie,
// reset hasła i weryfikacja adresu e-mail).
import { api } from "@/api/client";

export const accountApi = {
  get: () => api.get("/account"),
  updateProfile: (fields) => api.put("/account/profile", fields),
  updatePassword: (fields) => api.put("/account/password", fields),
  tokens: () => api.get("/account/tokens"),
  revokeToken: (id) => api.delete(`/account/tokens/${id}`),
  export: () => api.get("/account/export"),
  // DELETE z treścią (hasło) – api.delete nie przyjmuje body
  destroy: (password) => api.request("DELETE", "/account", { password }),

  forgotPassword: (email) => api.post("/forgot-password", { email }, { auth: false }),
  resetPassword: (fields) => api.post("/reset-password", fields, { auth: false }),

  verifyEmail: (params) => api.post("/email/verify", params),
  resendVerification: () => api.post("/email/verification-notification"),
};

export default accountApi;
