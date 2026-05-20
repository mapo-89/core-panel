export type PasswordRequirementsMeta = {
    passwordRequirements: {
        matchField?: string
        minLength?: number
    }
}

export function passwordMinLengthMeta(
    minLength: number,
): PasswordRequirementsMeta {
    return {
        passwordRequirements: {
            minLength,
        },
    }
}

export function passwordMatchMeta(
    matchField = 'password',
): PasswordRequirementsMeta {
    return {
        passwordRequirements: {
            matchField,
        },
    }
}
