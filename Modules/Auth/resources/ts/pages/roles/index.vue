<script setup lang="ts">
import { useI18n } from 'vue-i18n'

// Import des composants enfants (les dialogues)
// Le composant créé précédemment

// ────────────────────────────────────────────────────────────────────────────────
// Options & Page Meta
// ────────────────────────────────────────────────────────────────────────────────
defineOptions({ name: 'AuthRoleIndex' })
definePage({
  meta: {
    action: 'access',
    subject: 'auth_roles',
  },
})

// ────────────────────────────────────────────────────────────────────────────────
// Logique métier (via Composable)
// ────────────────────────────────────────────────────────────────────────────────
const { t } = useI18n()

const {
  loading,
  searchRoleKey,
  searchPermissionKey,
  cmpRoles,
  selectedRolesIds,
  selectedRoles,
  roleDialogState,
  permissionItems,
  permissionHeaders,
  selectedPermissions,
  detachPermission,
  selectedPermissionsIds,
  permissionDialogState,
  refreshAllData,
  openRoleEditDialog,
  openPermissionEditDialog,
  openPermissionsForRole,
  deleteRole,
  deleteManyRoles,
  deletePermission,
  deleteManyPermissions,
} = useAuthRoles()

const entity = 'role' as const // i18n key helper
</script>

<template>
  <VRow class="h-full">
    <!-- Rôles -->
    <VCol
      cols="12"
      md="5"
      lg="4"
    >
      <VCard class="vh-full">
        <VToolbar
          color="primary"
          class="text-white"
        >
          <VToolbarTitle>{{ t(`Auth.${entity}.titlePlural`) }}</VToolbarTitle>

          <VTextField
            v-model="searchRoleKey"
            :placeholder="t('action.search')"
            density="compact"
            variant="solo"
            single-line
            hide-details
            class="mx-auto pa-0 ml-4"
            append-inner-icon="bx-search"
            bg-color="white"
          />

          <VBtn
            v-if="$can('delete', 'auth_roles') && selectedRolesIds.length"
            class="me-2 ms-2"
            color="error"
            icon="mdi-delete"
            variant="tonal"
            :title="t('action.delete')"
            @click="deleteManyRoles(selectedRolesIds)"
          />

          <VBtn
            v-if="$can('add', 'auth_roles')"
            class="me-3 ms-2"
            color="success"
            :title="t('action.add')"
            icon="mdi-plus-circle-outline"
            variant="tonal"
            @click="openRoleEditDialog"
          />
        </VToolbar>

        <VList
          density="compact"
          lines="three"
        >
          <VListItem
            v-for="item in cmpRoles"
            :key="item.id"
            class="py-3 border-b"
          >
            <VListItemTitle>
              <VCheckbox
                v-model="selectedRolesIds"
                :value="item.id"
                :label="item.display_name"
                class="py-0"
                hide-details
              />
            </VListItemTitle>

            <VListItemSubtitle class="mb-1 text-medium-emphasis ml-8">
              {{ item.description }}
            </VListItemSubtitle>

            <template #append>
              <div class="d-flex flex-column align-end">
                <div class="mb-2">
                  <VChip
                    size="x-small"
                    variant="tonal"
                    color="info"
                    class="me-2"
                  >
                    {{ item.users_count }} {{ t('Auth.role.field.users_count') }}
                  </VChip>
                  <VChip
                    size="x-small"
                    variant="tonal"
                    color="secondary"
                  >
                    {{ item.permissions_count }} {{ t('Auth.role.field.permissions_count') }}
                  </VChip>
                </div>

                <div v-if="item.name !== 'administrator'">
                  <VBtn
                    :title="t('Auth.permission.titlePlural')"
                    size="small"
                    variant="tonal"
                    color="info"
                    class="pa-1 mx-1"
                    icon="mdi-menu-close"
                    @click="openPermissionsForRole(item.id)"
                  />

                  <VMenu open-on-hover>
                    <template #activator="{ props: menuProps }">
                      <VBtn
                        icon="mdi-dots-vertical"
                        variant="outlined"
                        v-bind="menuProps"
                        density="compact"
                        title="Actions"
                        class="ms-1"
                      />
                    </template>
                    <VList>
                      <VListItem
                        v-if="$can('edit', 'auth_roles')"
                        prepend-icon="bx-edit-alt"
                        :title="t('action.edit')"
                        @click="openRoleEditDialog(item)"
                      />
                      <VListItem
                        v-if="$can('delete', 'auth_roles')"
                        prepend-icon="bx-trash-alt"
                        :title="t('action.delete')"
                        @click="deleteRole(item.id)"
                      />
                    </VList>
                  </VMenu>
                </div>
              </div>
            </template>
          </VListItem>
        </VList>
      </VCard>
    </VCol>

    <VCol
      cols="12"
      md="7"
      lg="8"
    >
      <VCard class="vh-full">
        <VCardTitle class="d-flex justify-space-between align-center py-4">
          <h2 class="text-h6">
            {{ t('Auth.permission.titlePlural') }}
          </h2>
          <VTextField
            v-model="searchPermissionKey"
            :placeholder="t('action.search')"
            density="compact"
            variant="solo"
            single-line
            hide-details
            class="mx-auto pa-0 ml-4"
            append-inner-icon="bx-search"
            bg-color="white"
          />
          <VSpacer />
          <div class="d-flex align-center">
            <VBtn
              v-if="selectedRolesIds.length && $can('attach', 'auth_permissions') && !selectedPermissionsIds.length"
              class="ms-2"
              color="primary"
              title="Attacher les permissions"
              icon="mdi-link-variant-plus"
              @click="permissionDialogState.showAttach = true"
            />
            <VBtn
              v-if="selectedRolesIds.length && $can('attach', 'auth_permissions') && selectedPermissionsIds.length"
              class="ms-2"
              color="error"
              title="Détacher les permissions"
              icon="mdi-link-variant-minus"
              @click="detachPermission"
            />
            <VBtn
              v-if="$can('browse', 'auth_permissions')"
              class="ms-2"
              color="secondary"
              :title="t('action.refresh')"
              icon="bx-refresh"
              variant="tonal"
              @click="refreshAllData"
            />

            <VMenu open-on-hover>
              <template #activator="{ props: menuProps }">
                <VBtn
                  icon="mdi-dots-vertical"
                  variant="outlined"
                  v-bind="menuProps"
                  title="Actions"
                  class="ms-2"
                />
              </template>
              <VList>
                <VListItem
                  v-if="$can('add', 'auth_permissions')"
                  prepend-icon="bx-plus"
                  :title="t('action.add')"
                  @click="openPermissionEditDialog"
                />
                <VListItem
                  v-if="$can('delete', 'auth_permissions')"
                  prepend-icon="mdi-delete"
                  :title="t('action.delete')"
                  @click="deleteManyPermissions(selectedPermissionsIds)"
                />
              </VList>
            </VMenu>
          </div>
        </VCardTitle>

        <VDivider />

        <div class="pb-0 my-2">
          <h3>Rôles sélectionnés :</h3>
          <VChip
            v-for="role in selectedRoles"
            :key="role.id"
            class="me-2 mb-2"
            color="primary"
            variant="tonal"
            closable
            @click:close="selectedRolesIds = selectedRolesIds.filter(id => id !== role.id)"
          >
            {{ role.display_name }}
          </VChip>

          <p
            v-if="!selectedRoles.length"
            class="text-grey text-caption mt-2"
          >
            Sélectionnez un ou plusieurs rôles pour afficher leurs permissions.
          </p>
        </div>

        <VCardText class="pt-2">
          <VDataTable
            v-model="selectedPermissionsIds"
            entity="permission"
            :disabled="loading"
            :loading="loading"
            :headers="permissionHeaders"
            :items="permissionItems"
            :search="searchPermissionKey"
            items-per-page="15"
            fixed-header

            show-select
          >
            <template #item.data-table-expand="{ internalItem, isExpanded, toggleExpand }">
              <VBtn
                :icon="isExpanded(internalItem) ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                variant="plain"
                size="small"
                @click="toggleExpand(internalItem)"
              />
            </template>

            <!--
              <template #expanded-row="{ item: slotItem }">
              <td
              :colspan="permissionHeaders.length"
              class="pa-0"
              >
              <div class="d-flex justify-center bg-grey-lighten-4">
              <PermissionCard
              :permission="slotItem"
              class="my-4"
              style="max-width: 600px;"
              @edit="openPermissionEditDialog(slotItem)"
              @delete="deletePermission(slotItem.id)"
              />
              </div>
              </td>
              </template>
            -->

            <template #item.actions="{ item: permissionItem }">
              <div class="d-flex flex-nowrap align-center justify-end">
                <VMenu open-on-hover>
                  <template #activator="{ props: menuProps }">
                    <VBtn
                      icon="mdi-dots-vertical"
                      variant="outlined"
                      v-bind="menuProps"
                      density="compact"
                      title="Actions"
                    />
                  </template>
                  <VList>
                    <VListItem
                      v-if="$can('edit', 'auth_permissions')"
                      prepend-icon="bx-edit-alt"
                      :title="t('action.edit')"
                      @click="openPermissionEditDialog(permissionItem)"
                    />
                    <VListItem
                      v-if="$can('delete', 'auth_permissions')"
                      prepend-icon="bx-trash-alt"
                      :title="t('action.delete')"
                      @click="deletePermission(permissionItem.id)"
                    />
                  </VList>
                </VMenu>
              </div>
            </template>
          </VDataTable>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <AuthRoleAddOrEdit
    v-model="roleDialogState.show"
    :readonly="roleDialogState.readOnly"
    :item="roleDialogState.item"
    @saved="refreshAllData"
  />

  <AuthPermissionAddOrEdit
    v-model="permissionDialogState.showEdit"
    :item="permissionDialogState.item"
    @saved="refreshAllData"
  />

  <AuthPermissionAttachDialog
    v-model="permissionDialogState.showAttach"
    :roles="selectedRolesIds"
    @saved="refreshAllData"
  />
</template>
