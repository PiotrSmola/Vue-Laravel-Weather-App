export default [
  {
    path: "/alerts",
    name: "alerts",
    component: () => import("@/views/AlertsView.vue"),
    meta: { requiresAuth: true },
  },
];
