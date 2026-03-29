package br.dev.pulz.queuemaster.mobile.core.network.dto.auth

import br.dev.pulz.queuemaster.mobile.core.model.AuthenticatedUser
import br.dev.pulz.queuemaster.mobile.core.model.UserProfile
import br.dev.pulz.queuemaster.mobile.core.network.NetworkConfig

data class AuthGoogleRequestDto(
    val idToken: String
)

data class AuthGoogleResponseDto(
    val user: UserDto,
    val isNewUser: Boolean
)

data class AuthMeResponseDto(
    val user: UserDto
)

data class LogoutResponseDto(
    val message: String
)

data class UserDto(
    val id: Int,
    val name: String,
    val email: String,
    val role: String? = null,
    val phone: String? = null,
    val avatarUrl: String? = null,
    val effectiveRole: String? = null,
    val roleSummary: Map<String, Any?>? = null
)

fun UserDto.toAuthenticatedUser(): AuthenticatedUser {
    val roles = listOfNotNull(role, effectiveRole).distinct()
    return AuthenticatedUser(
        id = id,
        name = name,
        email = email,
        avatarUrl = NetworkConfig.resolveAbsoluteUrl(avatarUrl),
        roles = roles
    )
}

fun UserDto.toUserProfile(): UserProfile {
    return UserProfile(
        id = id,
        fullName = name,
        email = email,
        phoneNumber = phone,
        avatarUrl = NetworkConfig.resolveAbsoluteUrl(avatarUrl)
    )
}
