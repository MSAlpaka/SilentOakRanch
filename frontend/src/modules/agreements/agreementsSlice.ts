import { createAsyncThunk, createSlice } from '@reduxjs/toolkit'
import {
  giveConsent as giveConsentApi,
  loadAll as loadAllApi,
  loadOne as loadOneApi,
  uploadAgreement as uploadAgreementApi,
  Agreement,
  UploadAgreementPayload,
} from '../../api/agreements'

interface AgreementsState {
  items: Agreement[]
  loading: boolean
  error?: string
}

const initialState: AgreementsState = {
  items: [],
  loading: false,
}

export const loadAll = createAsyncThunk('agreements/loadAll', async () => {
  return await loadAllApi()
})

export const loadOne = createAsyncThunk('agreements/loadOne', async (id: string) => {
  return await loadOneApi(id)
})

export const giveConsent = createAsyncThunk('agreements/giveConsent', async (type: string) => {
  return await giveConsentApi(type)
})

export const uploadAgreement = createAsyncThunk(
  'agreements/upload',
  async (payload: UploadAgreementPayload) => {
    return await uploadAgreementApi(payload)
  }
)

const agreementsSlice = createSlice({
  name: 'agreements',
  initialState,
  reducers: {},
  extraReducers: builder => {
    builder.addCase(loadAll.fulfilled, (state, action) => {
      state.items = action.payload
    })
    builder.addCase(giveConsent.fulfilled, (state, action) => {
      state.items.push(action.payload)
    })
    builder.addCase(uploadAgreement.fulfilled, (state, action) => {
      state.items.push(action.payload)
    })
    builder
      .addMatcher(
        action => action.type.startsWith('agreements/') && action.type.endsWith('/pending'),
        state => {
          state.loading = true
          state.error = undefined
        }
      )
      .addMatcher(
        action => action.type.startsWith('agreements/') && action.type.endsWith('/fulfilled'),
        state => {
          state.loading = false
        }
      )
      .addMatcher(
        action => action.type.startsWith('agreements/') && action.type.endsWith('/rejected'),
        (state, action) => {
          state.loading = false
          state.error = action.error.message
        }
      )
  },
})

export default agreementsSlice.reducer

