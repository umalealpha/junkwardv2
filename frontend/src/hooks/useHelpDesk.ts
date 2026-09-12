import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  fetchHelpDeskTickets,
  fetchHelpDeskTicket,
  fetchHelpDeskSummary,
  fetchHelpDeskAudit,
  fetchHelpDeskComments,
  addHelpDeskComment,
  fetchHelpDeskSla,
  fetchSlaDashboard,
  createHelpDeskTicket,
  assignHelpDeskTicket,
  updateHelpDeskStatus,
  reopenHelpDeskTicket,
  cloneHelpDeskTicket,
  closeHelpDeskTicket,
  updateHelpDeskTicket,
  addHelpDeskAttachment,
  deleteHelpDeskAttachment,
  type HelpDeskFilters,
  type HelpDeskStatus,
  type CreateHelpDeskTicketPayload,
  type UpdateHelpDeskTicketPayload,
} from '../api/helpdesk'

export function useHelpDeskTickets(filters: HelpDeskFilters = {}) {
  return useQuery({
    queryKey: ['help-desk-tickets', filters],
    queryFn: () => fetchHelpDeskTickets(filters),
    placeholderData: (prev) => prev,
    staleTime: 60 * 1000,
  })
}

export function useHelpDeskSummary(filters: HelpDeskFilters = {}) {
  // Summary ignores page/per_page — strip them so identical scopes share cache.
  const { page: _p, per_page: _pp, ...scope } = filters
  return useQuery({
    queryKey: ['help-desk-summary', scope],
    queryFn: () => fetchHelpDeskSummary(scope),
    placeholderData: (prev) => prev,
    staleTime: 60 * 1000,
  })
}

export function useHelpDeskTicket(id: number) {
  return useQuery({
    queryKey: ['help-desk-ticket', id],
    queryFn: () => fetchHelpDeskTicket(id),
    enabled: !!id,
  })
}

export function useHelpDeskAudit(id: number) {
  return useQuery({
    queryKey: ['help-desk-audit', id],
    queryFn: () => fetchHelpDeskAudit(id),
    enabled: !!id,
  })
}

export function useHelpDeskComments(id: number) {
  return useQuery({
    queryKey: ['help-desk-comments', id],
    queryFn: () => fetchHelpDeskComments(id),
    enabled: !!id,
  })
}

export function useSlaDashboard() {
  return useQuery({
    queryKey: ['help-desk-sla-dashboard'],
    queryFn: () => fetchSlaDashboard(),
    staleTime: 60 * 1000,
    refetchInterval: 60 * 1000,
  })
}

export function useHelpDeskSla(id: number) {
  return useQuery({
    queryKey: ['help-desk-sla', id],
    queryFn: () => fetchHelpDeskSla(id),
    enabled: !!id,
    // Remaining-time is live; refetch periodically so the panel stays current.
    refetchInterval: 60 * 1000,
  })
}

export function useAddHelpDeskComment(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['help-desk-add-comment', id],
    mutationFn: (comment: string) => addHelpDeskComment(id, comment),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['help-desk-comments', id] }),
  })
}

export function useCreateHelpDeskTicket() {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['help-desk-create'],
    mutationFn: (payload: CreateHelpDeskTicketPayload) => createHelpDeskTicket(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['help-desk-tickets'] }),
  })
}

export function useAssignHelpDeskTicket(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['help-desk-assign', id],
    mutationFn: (assigneeEmail: string | null) => assignHelpDeskTicket(id, assigneeEmail),
    onSuccess: (data) => {
      qc.setQueryData(['help-desk-ticket', id], data)
      qc.invalidateQueries({ queryKey: ['help-desk-tickets'] })
      qc.invalidateQueries({ queryKey: ['help-desk-audit', id] })
    },
  })
}

export function useUpdateHelpDeskStatus(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['help-desk-status', id],
    mutationFn: (status: HelpDeskStatus) => updateHelpDeskStatus(id, status),
    onSuccess: (data) => {
      qc.setQueryData(['help-desk-ticket', id], data)
      qc.invalidateQueries({ queryKey: ['help-desk-tickets'] })
      qc.invalidateQueries({ queryKey: ['help-desk-audit', id] })
    },
  })
}

export function useReopenHelpDeskTicket(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['help-desk-reopen', id],
    mutationFn: (reason: string) => reopenHelpDeskTicket(id, reason),
    onSuccess: (data) => {
      qc.setQueryData(['help-desk-ticket', id], data)
      qc.invalidateQueries({ queryKey: ['help-desk-tickets'] })
      qc.invalidateQueries({ queryKey: ['help-desk-audit', id] })
    },
  })
}

/** Clone a ticket into a new related ticket. Returns the new ticket detail. */
export function useCloneHelpDeskTicket(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['help-desk-clone', id],
    mutationFn: () => cloneHelpDeskTicket(id),
    onSuccess: (data) => {
      qc.setQueryData(['help-desk-ticket', data.id], data)
      qc.invalidateQueries({ queryKey: ['help-desk-tickets'] })
      qc.invalidateQueries({ queryKey: ['help-desk-audit', id] })
    },
  })
}

export function useCloseHelpDeskTicket(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['help-desk-close', id],
    mutationFn: ({ summary, screenshot }: { summary: string; screenshot: File }) =>
      closeHelpDeskTicket(id, summary, screenshot),
    onSuccess: (data) => {
      qc.setQueryData(['help-desk-ticket', id], data)
      qc.invalidateQueries({ queryKey: ['help-desk-tickets'] })
      qc.invalidateQueries({ queryKey: ['help-desk-audit', id] })
    },
  })
}

export function useUpdateHelpDeskTicket(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['help-desk-update', id],
    mutationFn: (payload: UpdateHelpDeskTicketPayload) => updateHelpDeskTicket(id, payload),
    onSuccess: (data) => {
      qc.setQueryData(['help-desk-ticket', id], data)
      qc.invalidateQueries({ queryKey: ['help-desk-tickets'] })
    },
  })
}

export function useAddHelpDeskAttachment(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['help-desk-add-attachment', id],
    mutationFn: ({ file, slot }: { file: File; slot?: string }) => addHelpDeskAttachment(id, file, slot),
    onSuccess: (data) => qc.setQueryData(['help-desk-ticket', id], data),
  })
}

export function useDeleteHelpDeskAttachment(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['help-desk-delete-attachment', id],
    mutationFn: (index: number) => deleteHelpDeskAttachment(id, index),
    onSuccess: (data) => qc.setQueryData(['help-desk-ticket', id], data),
  })
}
