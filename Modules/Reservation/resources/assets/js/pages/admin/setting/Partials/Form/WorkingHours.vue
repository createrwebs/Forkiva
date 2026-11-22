<!-- The shared form is an intentional parent-owned form controller. -->
<!-- eslint-disable vue/no-mutating-props -->
<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import TimePicker from '@/modules/core/components/Form/TimePicker.vue'

defineProps<{
  form: any
  days: readonly string[]
}>()

const { t } = useI18n()
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex justify-space-between align-center mb-2">
      <div class="d-flex align-center">
        <VIcon
          class="me-2"
          icon="tabler-clock-hour-4"
          size="20"
        />
        <span>{{ t('reservation::attributes.reservation_settings.working_hours') }}</span>
      </div>
    </VCardTitle>

    <VCardText>
      <VTable
        class="working-hours-table"
        density="comfortable"
      >
        <thead>
          <tr>
            <th>
              {{ t('reservation::attributes.reservation_settings.working_hours') }}
            </th>
            <th class="text-center">{{ t('admin::admin.table.status') }}</th>
            <th>{{ t('reservation::frontend.open') }}</th>
            <th>{{ t('reservation::frontend.close') }}</th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="day in days"
            :key="day"
          >
            <td class="font-weight-medium">
              {{ t(`reservation::frontend.days.${day}`) }}
            </td>

            <td class="text-center">
              <VCheckbox
                v-model="form.state.working_hours[day].enabled"
                class="d-inline-flex"
                hide-details
              />
            </td>

            <td class="working-hours-table__time-cell">
              <TimePicker
                v-model="form.state.working_hours[day].open"
                :disabled="!form.state.working_hours[day].enabled"
                :error="!!form.errors.value?.[`working_hours.${day}.open`]"
                :error-messages="form.errors.value?.[`working_hours.${day}.open`]"
                label=""
              />
            </td>

            <td class="working-hours-table__time-cell">
              <TimePicker
                v-model="form.state.working_hours[day].close"
                :disabled="!form.state.working_hours[day].enabled"
                :error="!!form.errors.value?.[`working_hours.${day}.close`]"
                :error-messages="form.errors.value?.[`working_hours.${day}.close`]"
                label=""
              />
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.working-hours-table {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  overflow: hidden;
}

.working-hours-table :deep(th),
.working-hours-table :deep(td) {
  padding: 8px !important;
}

.working-hours-table__time-cell {
  min-inline-size: 220px;
}
</style>
