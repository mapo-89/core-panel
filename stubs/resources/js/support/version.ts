import versionInfo from '../../config/app-version.json'

type VersionInfo = {
    release_version?: string
    display_version?: string
    image_version?: string
    commit?: string
    commit_date?: string
}

const typedVersionInfo = versionInfo as VersionInfo

export const APP_VERSION = typedVersionInfo.display_version ?? ''
export const APP_RELEASE_VERSION = typedVersionInfo.release_version ?? ''
export const APP_IMAGE_VERSION = typedVersionInfo.image_version ?? ''
export const APP_COMMIT = typedVersionInfo.commit ?? ''
export const APP_COMMIT_DATE = typedVersionInfo.commit_date ?? ''

export function formatCommitDate(value: string | null | undefined): string {
    if (!value) {
        return ''
    }

    const parsed = new Date(value)

    if (Number.isNaN(parsed.getTime())) {
        return value
    }

    return new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(parsed)
}
