import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
})

api.defaults.withCredentials = true

export default api
