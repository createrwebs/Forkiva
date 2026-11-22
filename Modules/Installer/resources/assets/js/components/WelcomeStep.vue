<script setup lang="ts">
import InstallerIcon from './InstallerIcon.vue'
</script>

<template>
  <section
    class="welcome-content"
    aria-label="Prepare your restaurant workspace"
  >
    <div class="welcome-details">
      <div>
        <InstallerIcon name="server" />
        <div>
          <h2>Prepare your workspace</h2>
          <p>Check your server and connect an empty database.</p>
        </div>
      </div>
      <div>
        <InstallerIcon name="user" />
        <div>
          <h2>Make it yours</h2>
          <p>Create your administrator and get ready for service.</p>
        </div>
      </div>
    </div>
    <div class="notice welcome-notice">
      <InstallerIcon name="lock" />
      <p>Have your database credentials ready. Keep this session open until setup is complete.</p>
    </div>
  </section>
</template>
