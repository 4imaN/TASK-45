import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const routes = [
  { path: '/login', name: 'login', component: () => import('../views/auth/LoginView.vue'), meta: { guest: true } },
  { path: '/change-password', name: 'change-password', component: () => import('../views/auth/ChangePasswordView.vue') },
  { path: '/catalog', name: 'catalog', component: () => import('../views/student/CatalogView.vue') },
  { path: '/catalog/:id', name: 'catalog-detail', component: () => import('../views/student/ResourceDetailView.vue') },
  { path: '/loans', name: 'loans', component: () => import('../views/student/LoansView.vue') },
  { path: '/reservations', name: 'reservations', component: () => import('../views/student/ReservationsView.vue') },
  { path: '/membership', name: 'membership', component: () => import('../views/student/MembershipView.vue') },
  { path: '/recommendations', name: 'recommendations', component: () => import('../views/student/RecommendationsView.vue') },
  { path: '/approvals', name: 'approvals', component: () => import('../views/teaching/ApprovalsView.vue'), meta: { staff: true } },
  { path: '/transfers', name: 'transfers', component: () => import('../views/teaching/TransfersView.vue'), meta: { staff: true } },
  { path: '/checkout', name: 'checkout', component: () => import('../views/teaching/CheckoutView.vue'), meta: { staff: true } },
  { path: '/admin', name: 'admin', component: () => import('../views/admin/AdminDashboard.vue'), meta: { admin: true } },
  { path: '/admin/scopes', name: 'admin-scopes', component: () => import('../views/admin/ScopeManagement.vue'), meta: { admin: true } },
  { path: '/admin/holds', name: 'admin-holds', component: () => import('../views/admin/HoldsView.vue'), meta: { admin: true } },
  { path: '/admin/audit', name: 'admin-audit', component: () => import('../views/admin/AuditLogView.vue'), meta: { admin: true } },
  { path: '/admin/allowlists', name: 'admin-allowlists', component: () => import('../views/admin/AllowlistView.vue'), meta: { admin: true } },
  { path: '/admin/blacklists', name: 'admin-blacklists', component: () => import('../views/admin/BlacklistView.vue'), meta: { admin: true } },
  { path: '/admin/interventions', name: 'admin-interventions', component: () => import('../views/admin/InterventionLogView.vue'), meta: { admin: true } },
  { path: '/data-quality', name: 'data-quality', component: () => import('../views/admin/DataQualityView.vue'), meta: { staff: true } },
  { path: '/data-quality/import', name: 'data-quality-import', component: () => import('../views/admin/ImportView.vue'), meta: { staff: true } },
  { path: '/data-quality/aliases', name: 'data-quality-aliases', component: () => import('../views/admin/AliasManagementView.vue'), meta: { staff: true } },
  { path: '/', redirect: '/catalog' },
];

const router = createRouter({ history: createWebHistory(), routes });

router.beforeEach((to, from, next) => {
  const auth = useAuthStore();
  if (!to.meta.guest && !auth.isAuthenticated) return next('/login');
  if (to.meta.guest && auth.isAuthenticated) return next('/catalog');
  if (to.meta.admin && !auth.isAdmin) return next('/catalog');
  if (to.meta.staff && !auth.isStaff) return next('/catalog');
  if (auth.user?.force_password_change && to.name !== 'change-password' && to.name !== 'login') return next('/change-password');
  next();
});

export default router;
