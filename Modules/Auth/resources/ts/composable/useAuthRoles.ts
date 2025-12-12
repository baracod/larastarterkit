// src/composables/auth/useAuthRoles.ts
import { PermissionAPI } from '@auth/api/Permission'
import type { IPermission, IRole } from '@auth/types/entities'

// import { useTranslater } from '@/composables/useTranslater'
import { RoleAPI } from '@auth/api/Role'

export function useAuthRoles() {
  const { confirmDialog } = useDialog()
  const loading = ref<boolean>(false)
  const { t } = useI18n()

  // ──────────────────────────────────────────
  // Rôles
  // ──────────────────────────────────────────
  const roles = ref<IRole[]>([])
  const selectedRolesIds = ref<number[]>([])
  const searchRoleKey = ref<string>('')
  const searchPermissionKey = ref<string>('')

  // Dialogues Rôle
  const roleDialogState = reactive({
    show: false,
    item: null as IRole | null,
    readOnly: false,
  })

  // Logique de récupération des Rôles
  const fetchRoles = async () => {
    loading.value = true
    try {
      const data = await RoleAPI.getAll()

      roles.value = Array.isArray(data) ? data : []
    }
    catch (error) {
      console.error('Erreur lors du chargement des rôles:', error)
    }
    finally {
      loading.value = false
    }
  }

  // Logique de suppression des Rôles
  const deleteRole = async (id: number) => {
    const confirm = await confirmDialog(
      {
        color: 'error',
        title: t('message.confirmation.delete.title'),
        message: t('message.confirmation.delete.message'),
      },
    )

    if (!confirm)
      return
    loading.value = true
    try {
      await RoleAPI.delete(id)
      await fetchRoles()
    }
    catch (error) {
      console.error('Erreur lors de la suppression du rôle:', error)
    }
    finally {
      loading.value = false
    }
  }

  const deleteManyRoles = async (ids: number[]) => {
    const confirm = await confirmDialog(
      {
        color: 'error',
        title: t('message.confirmation.deleteMultiple.title'),
        message: t('message.confirmation.deleteMultiple.message'),
      },
    )

    if (!ids.length || !(confirm))
      return
    loading.value = true
    try {
      await RoleAPI.deleteMultiple(ids)
      selectedRolesIds.value = [] // Réinitialiser la sélection
      await fetchRoles()
    }
    catch (error) {
      console.error('Erreur lors de la suppression multiple des rôles:', error)
    }
    finally {
      loading.value = false
    }
  }

  // Rôles sélectionnés (pour les chips d'affichage)
  const selectedRoles = computed<IRole[]>(() =>
    roles.value.filter((item: IRole) => selectedRolesIds.value.includes(item.id)),
  )

  const cmpRoles = computed<IRole[]>(() => {
    if (!searchRoleKey.value)
      return roles.value

    return roles.value.filter(role =>
      role.name.toLowerCase().includes(searchRoleKey.value.toLowerCase())
      || (role.description && role.description.toLowerCase().includes(searchRoleKey.value.toLowerCase())),
    )
  })

  // ──────────────────────────────────────────
  // Permissions
  // ──────────────────────────────────────────
  const { tHeaderCols } = useTranslater()
  const permissionItems = ref<IPermission[]>([])
  const selectedPermissionsIds = ref<number[]>([])

  // Dialogues Permission
  const permissionDialogState = reactive({
    showEdit: false,
    showAttach: false,
    item: undefined as IPermission | undefined,
  })

  const permissionHeaders = tHeaderCols({
    entity: 'permission',
    module: 'Auth',
    headers: [
      { title: 'Key', key: 'key' },
      { title: 'Action', key: 'action' },
      { title: 'Subject', key: 'subject' },
      { title: 'Actions', key: 'actions', sortable: false, translate: false },
    ],
  })

  // Logique de récupération des Permissions
  const fetchAllPermissions = async () => {
    loading.value = true
    try {
      permissionItems.value = await PermissionAPI.getAll()
    }
    catch (error) {
      console.error('Erreur lors du chargement de toutes les permissions:', error)
      permissionItems.value = []
    }
    finally {
      loading.value = false
    }
  }

  const fetchPermissionsByRoleIds = async (ids: number[]) => {
    loading.value = true
    try {
      if (ids.length === 1)
        permissionItems.value = await RoleAPI.getPermissions(ids[0])
      else if (ids.length > 1)
        permissionItems.value = await RoleAPI.getCommonPermissions(ids)
      else
        await fetchAllPermissions() // Si aucune sélection, afficher toutes les permissions
    }
    catch (error) {
      console.error('Erreur lors du chargement des permissions des rôles:', error)
      await fetchAllPermissions() // Fallback
    }
    finally {
      loading.value = false
    }
  }

  const deletePermission = async (id: number) => {
    const confirm = await confirmDialog(
      {
        color: 'error',
        title: t('message.confirmation.delete.title'),
        message: t('message.confirmation.delete.message'),
      },
    )

    if (!confirm)
      return
    loading.value = true
    try {
      await PermissionAPI.delete(id)
      await fetchPermissionsByRoleIds(selectedRolesIds.value) // Rafraîchir les permissions selon la sélection
      await fetchRoles() // Rafraîchir les compteurs de permissions des rôles
    }
    catch (error) {
      console.error('Erreur lors de la suppression de la permission:', error)
    }
    finally {
      loading.value = false
    }
  }

  const deleteManyPermissions = async (ids: number[]) => {
    const confirm = await confirmDialog(
      {
        color: 'error',
        title: t('message.confirmation.deleteMultiple.title'),
        message: t('message.confirmation.deleteMultiple.message'),
      },
    )

    if (!ids.length || !confirm)
      return
    loading.value = true
    try {
      await PermissionAPI.deleteMultiple(ids)
      selectedPermissionsIds.value = [] // Réinitialiser la sélection
      await fetchAllPermissions() // Rafraîchir toutes les permissions
      await fetchRoles() // Rafraîchir les compteurs de permissions des rôles
    }
    catch (error) {
      console.error('Erreur lors de la suppression multiple des permissions:', error)
    }
    finally {
      loading.value = false
    }
  }

  const detachPermission = async () => {
    const confirm = await confirmDialog(
      {
        color: 'error',
        title: t('Auth.message.detachPermissions.title'),
        message: t('Auth.message.detachPermissions.message'),
      },
    )

    if (!confirm)
      return
    loading.value = true
    try {
      await RoleAPI.detachPermissions(selectedRolesIds.value, selectedPermissionsIds.value)
      selectedPermissionsIds.value = []
      await fetchPermissionsByRoleIds(selectedRolesIds.value) // Rafraîchir les permissions selon la sélection
      await fetchRoles() // Rafraîchir les compteurs de permissions des rôles
    }
    catch (error) {
      console.error('Erreur lors du détachement de la permission:', error)
    }
    finally {
      loading.value = false
    }
  }

  // Permissions sélectionnées (pour les chips d'affichage)

  const selectedPermissions = computed<IPermission[]>(() =>
    permissionItems.value.filter((item: IPermission) => selectedPermissionsIds.value.includes(item.id)),
  )

  const cmpPermissions = computed<IPermission[]>(() => {
    if (!searchPermissionKey.value)
      return permissionItems.value

    const search = searchPermissionKey.value.toLowerCase()

    return permissionItems.value.filter((permission => {
      return permission.key.toLowerCase().includes(search)
        || permission?.action?.toLowerCase().includes(search)
        || permission?.subject?.toLowerCase().includes(search)
    }))
  })

  // ──────────────────────────────────────────
  // Initialisation & Watchers
  // ──────────────────────────────────────────

  // Watcher pour charger les permissions quand la sélection de rôles change
  watch(
    selectedRolesIds,
    newIds => fetchPermissionsByRoleIds(newIds),
    { deep: true, immediate: false },
  )

  onMounted(() => {
    fetchRoles()
    fetchAllPermissions() // Charge toutes les permissions au démarrage avant toute sélection
  })

  // ──────────────────────────────────────────
  // Fonctions d'Orchestration
  // ──────────────────────────────────────────

  const refreshAllData = async () => {
    roleDialogState.show = false
    permissionDialogState.showAttach = false
    permissionDialogState.showEdit = false

    // Pour s'assurer que les compteurs sont à jour
    await fetchRoles()
    await fetchPermissionsByRoleIds(selectedRolesIds.value)
  }

  const openRoleEditDialog = (item?: IRole) => {
    roleDialogState.item = item ?? null
    roleDialogState.readOnly = false
    roleDialogState.show = true
  }

  const openPermissionEditDialog = (item?: IPermission) => {
    permissionDialogState.item = item ?? undefined
    permissionDialogState.showEdit = true
  }

  const openPermissionsForRole = (roleId: number) => {
    // Force la sélection unique pour ce rôle et déclenche le watcher
    selectedRolesIds.value = [roleId]
  }

  // Réinitialiser readOnly quand le dialogue se ferme
  watch(() => roleDialogState.show, isOpen => {
    if (!isOpen)
      roleDialogState.readOnly = false
  })

  return {
    // State
    loading,
    searchRoleKey,
    searchPermissionKey,

    // Rôles
    roles,
    selectedRolesIds,
    selectedRoles,
    roleDialogState,
    cmpRoles,

    // Permissions
    permissionItems,
    permissionHeaders,
    permissionDialogState,
    selectedPermissions,
    selectedPermissionsIds,
    cmpPermissions,
    detachPermission,

    // Actions
    refreshAllData,
    openRoleEditDialog,
    openPermissionEditDialog,
    openPermissionsForRole,
    deleteRole,
    deleteManyRoles,
    deletePermission,
    deleteManyPermissions,
  }
}
