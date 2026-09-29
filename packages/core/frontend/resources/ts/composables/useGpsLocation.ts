/**
 * Géolocalisation navigateur (API HTML5).
 * Utilisé pour remplir Latitude/Longitude en un clic sur le terrain.
 * Nommé useGpsLocation pour éviter le conflit avec useGeolocation de VueUse.
 */
const DECIMALS = 7

export function useGpsLocation() {
  const isLocating = ref(false)
  const notify = useNotify()

  /**
   * Obtient la position actuelle (GPS). Retourne { lat, lng } à 7 décimales.
   * Affiche une notification en cas d'erreur.
   */
  function getCurrentLocation(): Promise<{ lat: number; lng: number } | null> {
    if (!navigator?.geolocation) {
      notify.notify({
        type: 'error',
        title: 'Géolocalisation indisponible',
        message: 'Votre navigateur ou appareil ne prend pas en charge la géolocalisation.',
      })

      return Promise.resolve(null)
    }

    isLocating.value = true

    return new Promise(resolve => {
      navigator.geolocation.getCurrentPosition(
        position => {
          isLocating.value = false

          const lat = Number(Number(position.coords.latitude).toFixed(DECIMALS))
          const lng = Number(Number(position.coords.longitude).toFixed(DECIMALS))

          resolve({ lat, lng })
        },
        err => {
          isLocating.value = false

          const message
            = err.code === 1
              ? 'Position refusée. Autorisez l’accès à la position pour ce site.'
              : err.code === 2
                ? 'Position indisponible. Vérifiez le GPS ou la connexion.'
                : 'Impossible d’obtenir la position. Réessayez plus tard.'

          notify.notify({
            type: 'error',
            title: 'Erreur de géolocalisation',
            message,
          })
          resolve(null)
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 10000 },
      )
    })
  }

  return { getCurrentLocation, isLocating }
}
