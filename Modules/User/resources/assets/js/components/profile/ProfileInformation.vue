<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import { useForm } from '@/modules/core/composables/form.ts'
import { useAccount } from '@/modules/user/composables/account.ts'
import ProfileSection from './ProfileSection.vue'

const emit = defineEmits<{ (event: 'busy', value: boolean): void }>()
const { t } = useI18n()
const { can, store } = useAuth()
const { me, updateProfile } = useAccount()
const accountId = store.getUser?.id
const form = useForm({ name: '', email: '', username: '' })
const original = ref({ ...form.state })
const branch = ref('')
const gender = ref('')
const loading = ref(true)
const failed = ref(false)
const allowEdit = computed(() => can('admin.profiles.edit'))
const dirty = computed(() =>
  Object.keys(original.value).some(
    (key) =>
      form.state[key as keyof typeof form.state] !==
      original.value[key as keyof typeof original.value],
  ),
)
const disabled = computed(
  () =>
    loading.value ||
    failed.value ||
    !allowEdit.value ||
    !dirty.value ||
    form.loading.value ||
    !form.state.name.trim() ||
    !form.state.email.trim() ||
    !form.state.username.trim(),
)
watch(form.loading, (value) => emit('busy', value))

async function loadProfile() {
  loading.value = true
  failed.value = false
  try {
    const { data } = await me()
    Object.assign(form.state, {
      name: data.body.name,
      email: data.body.email,
      username: data.body.username,
    })
    original.value = { ...form.state }
    branch.value = data.body.branch?.name || ''
    gender.value = data.body.gender?.name || ''
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

async function submit() {
  if (disabled.value) return
  const submitted = { ...form.state }
  const response = await form.submit(() => updateProfile(submitted), true)
  if (response && typeof response !== 'boolean' && store.getUser?.id === accountId) {
    store.setUser(response.data.body.user)
    original.value = submitted
  }
}
onMounted(loadProfile)
</script>

<template>
  <form @submit.prevent="submit">
    <ProfileSection
      :title="t('user::profiles.information')"
      :description="t('user::profiles.information_description')"
    >
      <div
        v-if="loading"
        class="d-flex justify-center pa-10"
        role="status"
        :aria-label="t('user::profiles.loading')"
      >
        <VProgressCircular
          indeterminate
          color="primary"
        />
      </div>
      <VAlert
        v-else-if="failed"
        type="error"
        variant="tonal"
      >
        {{ t('core::errors.an_unexpected_error_occurred') }}
        <VBtn
          variant="text"
          @click="loadProfile"
        >
          {{ t('user::profiles.retry') }}
        </VBtn>
      </VAlert>
      <template v-else>
        <VAlert
          v-if="!allowEdit"
          class="mb-6"
          type="info"
          variant="tonal"
        >
          {{ t('user::profiles.read_only') }}
        </VAlert>
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="form.state.name"
              :label="t('user::attributes.users.name')"
              :readonly="!allowEdit"
              :disabled="form.loading.value"
              :error-messages="form.errors.value.name"
              autocomplete="name"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="form.state.email"
              :label="t('user::attributes.users.email')"
              :readonly="!allowEdit"
              :disabled="form.loading.value"
              :error-messages="form.errors.value.email"
              type="email"
              autocomplete="email"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="form.state.username"
              :label="t('user::attributes.users.username')"
              :readonly="!allowEdit"
              :disabled="form.loading.value"
              :error-messages="form.errors.value.username"
              autocomplete="username"
            />
          </VCol>
          <VCol
            v-if="branch"
            cols="12"
            md="6"
          >
            <VTextField
              :model-value="branch"
              :label="t('user::attributes.users.branch_id')"
              readonly
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              :model-value="gender"
              :label="t('user::attributes.users.gender')"
              readonly
            />
          </VCol>
        </VRow>
      </template>
      <template
        v-if="!loading && !failed && allowEdit"
        #actions
      >
        <VBtn
          type="submit"
          prepend-icon="tabler-check"
          :disabled="disabled"
          :loading="form.loading.value"
        >
          {{ t('user::profiles.save_changes') }}
        </VBtn>
      </template>
    </ProfileSection>
  </form>
</template>
