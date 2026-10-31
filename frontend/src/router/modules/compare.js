// Porównanie miast (trasa publiczna). Wybór zapisany w URL: /compare?ids=756135,3094802
export default [
  {
    path: "/compare",
    name: "compare",
    component: () => import("@/views/CompareView.vue"),
  },
];
