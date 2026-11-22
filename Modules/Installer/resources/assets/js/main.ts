import { createApp } from 'vue'
import InstallerApp from './InstallerApp.vue'
import './installer.css'
import type { InstallationState } from './contracts/installer'

const element = document.getElementById('installer')!
const initialState = JSON.parse(element.dataset.state!) as InstallationState
createApp(InstallerApp, { initialState }).mount(element)
