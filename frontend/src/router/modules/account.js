// Konto użytkownika, reset hasła i weryfikacja adresu e-mail
export default [
  {
    path: "/account",
    name: "account",
    component: () => import("@/views/Account/AccountView.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/forgot-password",
    name: "forgot-password",
    component: () => import("@/views/Auth/ForgotPasswordView.vue"),
    meta: { guest: true },
  },
  {
    path: "/reset-password",
    name: "reset-password",
    component: () => import("@/views/Auth/ResetPasswordView.vue"),
    meta: { guest: true },
  },
  {
    // Link z maila – wymaga zalogowania (po zalogowaniu powrót z ?redirect=)
    path: "/verify-email",
    name: "verify-email",
    component: () => import("@/views/Auth/VerifyEmailView.vue"),
    meta: { requiresAuth: true },
  },
];
