<script setup lang="ts">
import CoreFilterPanel from '@/components/CoreFilterPanel.vue'
import { useI18n } from 'vue-i18n'
import AuthModuleFrame from '../../components/AuthModuleFrame.vue'

// Import des composants enfants (les dialogues)
// Le composant créé précédemment

// ────────────────────────────────────────────────────────────────────────────────
// Options & Page Meta
// ────────────────────────────────────────────────────────────────────────────────
defineOptions({ name: 'AuthRoleIndex' })
definePage({
  meta: {
    action: 'browse',
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
const openNewPermissionDialog = () => openPermissionEditDialog()
</script>

<template>
  <AuthModuleFrame>
    <VRow class="h-full">
      <!-- Rôles -->
      <VCol
        cols="12"
        md="5"
        lg="4"
      >
        <VCard class="vh-full">
          <div class="auth-role-toolbar">
            <h2 class="text-h6">
              {{ t(`Auth.${entity}.titlePlural`) }}
            </h2>

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
          </div>
          <CoreFilterPanel
            class="ma-4"
            :active-count="searchRoleKey ? 1 : 0"
            @reset="searchRoleKey = ''"
          >
            <VTextField
              v-model="searchRoleKey"
              :label="t('Auth.design.searchRoles')"
              density="compact"
              variant="outlined"
              single-line
              hide-details

              append-inner-icon="bx-search"
            />
          </CoreFilterPanel>

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

              <VListItemSubtitle class="mb-1 text-medium-emphasis ms-8">
                {{ item.description }}
              </VListItemSubtitle>

              <div class="auth-role-summary">
                <div class="d-flex flex-wrap align-center gap-2">
                  <div class="d-flex flex-wrap gap-2 flex-grow-1">
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

                    <VMenu>
                      <template #activator="{ props: menuProps }">
                        <VBtn
                          icon="mdi-dots-vertical"
                          variant="outlined"
                          v-bind="menuProps"
                          density="compact"
                          :aria-label="t('actions')"
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
              </div>
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

            <VSpacer />
            <div class="d-flex align-center">
              <VBtn
                v-if="selectedRolesIds.length && $can('attach', 'auth_permissions') && !selectedPermissionsIds.length"
                class="ms-2"
                color="primary"
                :aria-label="t('Auth.design.attachPermissions')"
                icon="mdi-link-variant-plus"
                @click="permissionDialogState.showAttach = true"
              />
              <VBtn
                v-if="selectedRolesIds.length && $can('attach', 'auth_permissions') && selectedPermissionsIds.length"
                class="ms-2"
                color="error"
                :aria-label="t('Auth.design.detachPermissions')"
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

              <VMenu>
                <template #activator="{ props: menuProps }">
                  <VBtn
                    icon="mdi-dots-vertical"
                    variant="outlined"
                    v-bind="menuProps"
                    :aria-label="t('actions')"
                    class="ms-2"
                  />
                </template>
                <VList>
                  <VListItem
                    v-if="$can('add', 'auth_permissions')"
                    prepend-icon="bx-plus"
                    :title="t('action.add')"
                    @click="openNewPermissionDialog"
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
          <CoreFilterPanel
            class="ma-4"
            :active-count="searchPermissionKey ? 1 : 0"
            @reset="searchPermissionKey = ''"
          >
            <VTextField
              v-model="searchPermissionKey"
              :label="t('Auth.design.searchPermissions')"
              density="compact"
              variant="outlined"
              single-line
              hide-details

              append-inner-icon="bx-search"
            />
          </CoreFilterPanel>

          <VDivider />

          <div class="pa-4 pb-0 my-2">
            <h3>{{ t('Auth.design.selectedRoles') }}</h3>
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
              {{ t('Auth.design.chooseRoles') }}
            </p>
          </div>

          <VCardText class="pt-2">
            <VDataTable
              v-model="selectedPermissionsIds"
              :mobile-breakpoint="600"
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
                  <VMenu>
                    <template #activator="{ props: menuProps }">
                      <VBtn
                        icon="mdi-dots-vertical"
                        variant="outlined"
                        v-bind="menuProps"
                        density="compact"
                        :aria-label="t('actions')"
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
      :item="roleDialogState.item ?? undefined"
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
  </AuthModuleFrame>
</template>
