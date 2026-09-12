import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { lazy, Suspense } from 'react'
import ClaimsLayout from './components/ClaimsLayout'
import TokenGate from './components/TokenGate'

const Dashboard = lazy(() => import('./pages/Dashboard'))
const ClaimList = lazy(() => import('./pages/ClaimList'))
const ClaimCreate = lazy(() => import('./pages/ClaimCreate'))
const ClaimDetail = lazy(() => import('./pages/ClaimDetail'))
const ClaimEdit = lazy(() => import('./pages/ClaimEdit'))
const ReservesDashboard = lazy(() => import('./pages/ReservesDashboard'))
const ClaimReports = lazy(() => import('./pages/ClaimReports'))
const UwBottleneckDashboard = lazy(() => import('./pages/UwBottleneckDashboard'))

export default function App() {
  return (
    <BrowserRouter>
      <TokenGate>
        <Suspense fallback={<div className="flex items-center justify-center h-screen"><div className="animate-spin w-8 h-8 border-4 border-claims-primary border-t-transparent rounded-full" /></div>}>
          <Routes>
            <Route element={<ClaimsLayout />}>
              <Route index element={<Dashboard />} />
              <Route path="claims" element={<ClaimList />} />
              <Route path="claims/new" element={<ClaimCreate />} />
              <Route path="claims/:id" element={<ClaimDetail />} />
              <Route path="claims/:id/edit" element={<ClaimEdit />} />
              <Route path="reserves" element={<ReservesDashboard />} />
              <Route path="reports" element={<ClaimReports />} />
              <Route path="underwriting-bottleneck" element={<UwBottleneckDashboard />} />
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </Suspense>
      </TokenGate>
    </BrowserRouter>
  )
}
