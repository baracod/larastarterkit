/**
 * Optimise une image pour le web
 * @param file - Fichier image à optimiser
 * @param maxWidth - Largeur maximale (défaut: 400px)
 * @param quality - Qualité JPEG (0-1, défaut: 0.85)
 * @returns Promise<File> - Image optimisée
 */
export async function optimizeImage(
  file: File,
  maxWidth = 400,
  quality = 0.85,
): Promise<File> {
  return new Promise((resolve, reject) => {
    // Vérifier que c'est bien une image
    if (!file.type.startsWith('image/')) {
      reject(new Error('Le fichier doit être une image'))

      return
    }

    const reader = new FileReader()

    reader.onload = e => {
      const img = new Image()

      img.onload = () => {
        const canvas = document.createElement('canvas')
        let { width, height } = img

        // Redimensionner si nécessaire
        if (width > maxWidth) {
          height = (height * maxWidth) / width
          width = maxWidth
        }

        canvas.width = width
        canvas.height = height

        const ctx = canvas.getContext('2d')
        if (!ctx) {
          reject(new Error('Impossible de créer le contexte canvas'))

          return
        }

        // Dessiner l'image redimensionnée
        ctx.drawImage(img, 0, 0, width, height)

        // Convertir en blob JPEG
        canvas.toBlob(
          blob => {
            if (!blob) {
              reject(new Error('Erreur lors de la conversion de l\'image'))

              return
            }

            // Créer un nouveau fichier avec le blob optimisé
            const optimizedFile = new File([blob], file.name, {
              type: 'image/jpeg',
              lastModified: Date.now(),
            })

            resolve(optimizedFile)
          },
          'image/jpeg',
          quality,
        )
      }

      img.onerror = () => {
        reject(new Error('Erreur lors du chargement de l\'image'))
      }

      img.src = e.target!.result as string
    }

    reader.onerror = () => {
      reject(new Error('Erreur lors de la lecture du fichier'))
    }

    reader.readAsDataURL(file)
  })
}
