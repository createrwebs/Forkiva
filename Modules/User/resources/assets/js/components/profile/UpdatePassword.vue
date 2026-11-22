<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useForm } from '@/modules/core/composables/form.ts'
import { useAccount } from '@/modules/user/composables/account.ts'
import ProfileSection from './ProfileSection.vue'

const emit = defineEmits<{ (event: 'busy', value: boolean): void }>()
const { t } = useI18n()
const { updatePassword } = useAccount()
const initial = {
  current_password: '',
  password: '',
  password_confirmation: '',
  logout_from_other_devices: false,
}
const form = useForm(initial)
const visible = ref(false)
const disabled = computed(
  () =>
    form.loading.value ||
    !form.state.current_password ||
    !form.state.password ||
    !form.state.password_confirmation,
)
watch(form.loading, (value) => emit('busy', value))
async function submit() {
  if (disabled.value) return
  if (await form.submit(() => updatePassword({ ...form.state }))) {
    Object.assign(form.state, initial)
    visible.value = false
  }
}
</script>

<template>
  <form @submit.prevent="submit">
    <ProfileSection
      icon="tabler-lock"
      :title="t('admin::navbar.update_password')"
      :description="t('user::profiles.password_description')"
    >
      <VRow>
        <VCol cols="12">
          <VTextField
            v-model="form.state.current_password"
            :label="t('user::attributes.users.current_password')"
            :error-messages="form.errors.value.current_password"
            :disabled="form.loading.value"
            :type="visible ? 'text' : 'password'"
            autocomplete="current-password"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.password"
            :label="t('user::attributes.users.password')"
            :error-messages="form.errors.value.password"
            :disabled="form.loading.value"
            :type="visible ? 'text' : 'password'"
            autocomplete="new-password"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.password_confirmation"
            :label="t('user::attributes.users.password_confirmation')"
            :error-messages="form.errors.value.password_confirmation"
            :disabled="form.loading.value"
            :type="visible ? 'text' : 'password'"
            autocomplete="new-password"
          />
        </VCol>
      </VRow>
      <VCheckbox
        v-model="visible"
        :label="t('user::profiles.show_passwords')"
        :disabled="form.loading.value"
        hide-details
        class="mt-2"
      />
      <VCheckbox
        v-model="form.state.logout_from_other_devices"
        :label="t('user::attributes.users.logout_from_other_devices')"
        :disabled="form.loading.value"
        :error-messages="form.errors.value.logout_from_other_devices"
      />
      <template #actions>
        <VBtn
          type="submit"
          prepend-icon="tabler-lock"
          :disabled="disabled"
          :loading="form.loading.value"
        >
          {{ t('admin::navbar.update_password') }}
        </VBtn>
      </template>
    </ProfileSection>
  </form>
</template>
