import { StyleSheet } from 'react-native';
import { C } from './theme';

export default StyleSheet.create({
  // ===============================
  // Aplicação
  // ===============================

  dashboardContainer: {
    flex: 1,
    backgroundColor: C.background,
  },

  scrollBody: {
    flexGrow: 1,
    padding: 12,
    paddingBottom: 20,
  },

  // ===============================
  // Barra superior
  // ===============================

  topbar: {
    height: 58,
    marginTop: 10,
    backgroundColor: C.white,
    borderBottomWidth: 1,
    borderBottomColor: C.border,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
  },

  pageTitleHeading: {
    fontSize: 15,
    fontWeight: '700',
    color: C.textDark,
  },

  logoDotSmall: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: C.primary,
    marginRight: 8,
  },

  topbarUserIcon: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: C.background,
    alignItems: 'center',
    justifyContent: 'center',
  },

  // ===============================
  // Navegação inferior
  // ===============================

  bottomNav: {
    height: 68,
    flexDirection: 'row',
    justifyContent: 'space-around',
    alignItems: 'flex-start',
    backgroundColor: C.white,
    borderTopWidth: 1,
    borderTopColor: C.border,
    paddingTop: 18,
    paddingBottom: 0,
  },

  bottomNavItem: {
    flex: 1,
    height: '100%',
    alignItems: 'center',
    justifyContent: 'flex-start',
  },

  bottomNavText: {
    fontSize: 10,
    lineHeight: 12,
    marginTop: 3,
    color: C.textMuted,
  },

  bottomNavTextActive: {
    color: C.primary,
    fontWeight: '700',
  },

  // ===============================
  // Containers e títulos
  // ===============================

  whiteContainerBox: {
    backgroundColor: C.white,
    borderRadius: 14,
    padding: 14,
    borderWidth: 1,
    borderColor: C.border,
  },

  boxTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: C.textDark,
  },

  boxSub: {
    fontSize: 12,
    color: C.textMuted,
    marginTop: 3,
  },

  // ===============================
  // Login e cadastro
  // ===============================

  authContainer: {
    flex: 1,
    backgroundColor: C.primary,
  },

  authScroll: {
    flexGrow: 1,
    justifyContent: 'center',
    padding: 20,
  },

  authHeaderBox: {
    alignItems: 'center',
    marginBottom: 24,
  },

  logoContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },

  logoDot: {
    width: 15,
    height: 15,
    borderRadius: 8,
    backgroundColor: C.white,
    marginRight: 8,
  },

  logoText: {
    color: C.white,
    fontSize: 28,
    fontWeight: '700',
  },

  logoSubtext: {
    color: C.white,
    opacity: 0.9,
    marginTop: 8,
  },

  authCardBox: {
    backgroundColor: C.white,
    borderRadius: 14,
    padding: 18,
  },

  authTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: C.textDark,
    marginBottom: 18,
  },

  inputLabel: {
    fontSize: 12,
    color: C.textDark,
    fontWeight: '600',
    marginBottom: 5,
  },

  textInput: {
    height: 44,
    borderWidth: 1,
    borderColor: '#DC55F2',
    borderRadius: 8,
    paddingHorizontal: 12,
    backgroundColor: C.white,
    color: C.textDark,
    marginBottom: 10,
  },

  primaryButton: {
    height: 44,
    borderRadius: 8,
    backgroundColor: C.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },

  primaryButtonText: {
    color: C.white,
    fontWeight: '700',
  },

  // ===============================
  // Cards financeiros
  // ===============================

  kpiCard: {
    backgroundColor: C.white,
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
  },

  kpiLabel: {
    color: C.textMuted,
    fontSize: 12,
  },

  kpiValue: {
    fontSize: 24,
    fontWeight: '700',
    marginVertical: 4,
  },

  kpiSub: {
    color: C.textMuted,
    fontSize: 11,
  },

  sectionBoxLarge: {
    backgroundColor: C.white,
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
  },

  sectionBoxSmall: {
    backgroundColor: C.white,
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
  },

  summaryBadgeBox: {
    flex: 1,
    backgroundColor: C.background,
    borderRadius: 10,
    padding: 12,
    marginHorizontal: 4,
  },

  summaryBadgeLabel: {
    color: C.textMuted,
    fontSize: 11,
  },

  summaryBadgeVal: {
    color: C.danger,
    fontSize: 15,
    fontWeight: '700',
    marginTop: 4,
  },

  // ===============================
  // Botões
  // ===============================

  purpleActionButton: {
    minHeight: 42,
    borderRadius: 8,
    backgroundColor: C.primary,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
  },

  quickActionButton: {
    flex: 1,
    minHeight: 48,
    borderRadius: 8,
    backgroundColor: C.primary,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    marginHorizontal: 3,
  },

  quickActionText: {
    color: C.white,
    fontWeight: '700',
    fontSize: 12,
  },

  // ===============================
  // Movimentações
  // ===============================

  movRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#F0F0F0',
  },

  movIcon: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },

  movText: {
    color: C.textDark,
    fontWeight: '700',
    fontSize: 13,
  },

  movVal: {
    color: C.textMuted,
    fontSize: 11,
    marginTop: 2,
  },

  mobileCardItem: {
    backgroundColor: C.white,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: C.border,
    padding: 12,
  },

  // ===============================
  // Metas
  // ===============================

  metaBanner: {
    backgroundColor: C.primary,
    borderRadius: 10,
    padding: 14,
  },

  metaBannerTitle: {
    color: C.white,
    fontSize: 15,
    fontWeight: '700',
  },

  metaBannerSub: {
    color: C.white,
    opacity: 0.9,
    fontSize: 12,
    marginTop: 4,
  },

  metaCardItem: {
    backgroundColor: '#FAFAFA',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: C.border,
    padding: 12,
    marginBottom: 10,
  },

  metaCardTitle: {
    color: C.textDark,
    fontWeight: '700',
    fontSize: 14,
  },

  metaCardDesc: {
    color: C.textMuted,
    fontSize: 12,
    marginTop: 4,
  },

  progressBg: {
    height: 9,
    borderRadius: 5,
    backgroundColor: '#E8DCE9',
    overflow: 'hidden',
    marginTop: 10,
  },

  progressFill: {
    height: '100%',
    borderRadius: 5,
    backgroundColor: C.primary,
  },

  // ===============================
  // Relatório
  // ===============================

  reportRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: C.border,
  },

  reportLabel: {
    color: C.textMuted,
  },

  reportValue: {
    color: C.textDark,
    fontWeight: '700',
  },

  // ===============================
  // Modal
  // ===============================

  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(51, 51, 51, 0.45)',
    justifyContent: 'center',
    padding: 18,
  },

  modalContent: {
    backgroundColor: C.white,
    borderRadius: 14,
    padding: 16,
  },

  modalTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: C.textDark,
    marginBottom: 14,
  },

  sectionTitle: {
    color: C.textDark,
    fontSize: 12,
    fontWeight: '700',
    marginBottom: 8,
  },

  typeBtn: {
    flex: 1,
    height: 38,
    borderWidth: 1,
    borderColor: C.border,
    borderRadius: 8,
    marginHorizontal: 3,
    alignItems: 'center',
    justifyContent: 'center',
  },

  modalBtnCancel: {
    flex: 1,
    height: 42,
    borderWidth: 1,
    borderColor: C.border,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 6,
  },

  modalBtnSave: {
    flex: 1,
    height: 42,
    borderRadius: 8,
    backgroundColor: C.primary,
    alignItems: 'center',
    justifyContent: 'center',
    marginLeft: 6,
  },

  emptyText: {
    color: C.textMuted,
    fontSize: 12,
    textAlign: 'center',
    paddingVertical: 14,
  },
});