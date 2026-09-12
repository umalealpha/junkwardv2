import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { lazy, Suspense } from 'react'
import AceLayout from './components/AceLayout'
import TokenGate from './components/TokenGate'

const Dashboard = lazy(() => import('./pages/Dashboard'))
const Rules = lazy(() => import('./pages/Rules'))
const Targets = lazy(() => import('./pages/Targets'))
const Ledger = lazy(() => import('./pages/Ledger'))
const FraudAlerts = lazy(() => import('./pages/FraudAlerts'))
const AgentScorecard = lazy(() => import('./pages/AgentScorecard'))

export default function App() {
  return (
    <BrowserRouter>
      <TokenGate>
        <Suspense fallback={<div className="flex items-center justify-center h-screen"><div className="animate-spin w-8 h-8 border-4 border-ace-primary border-t-transparent rounded-full" /></div>}>
          <Routes>
            <Route element={<AceLayout />}>
              <Route index element={<Dashboard />} />
              <Route path="rules" element={<Rules />} />
              <Route path="targets" element={<Targets />} />
              <Route path="ledger" element={<Ledger />} />
              <Route path="fraud-alerts" element={<FraudAlerts />} />
              <Route path="agent/:agentId" element={<AgentScorecard />} />
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </Suspense>
      </TokenGate>
    </BrowserRouter>
  )
}
