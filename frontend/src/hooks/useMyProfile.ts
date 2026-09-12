import { useQuery } from '@tanstack/react-query'
import { fetchMyProfile } from '../api/me'

export function useMyProfile() {
  return useQuery({
    queryKey: ['me', 'profile'],
    queryFn: fetchMyProfile,
    staleTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}
