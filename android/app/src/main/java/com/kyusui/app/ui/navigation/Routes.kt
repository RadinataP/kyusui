package com.kyusui.app.ui.navigation

import android.net.Uri
import com.kyusui.app.domain.model.Product

/**
 * Argumen navigasi untuk detail produk.
 *
 * Nilainya diambil dari `GET /products` dan diteruskan lewat query args
 * karena specification tidak menyediakan `GET /products/{id}`.
 */
data class ProductRouteArgs(
    val productId: String,
    val name: String,
    val price: String,
    val description: String?,
    val isAvailable: Boolean
) {
    companion object {
        fun from(product: Product): ProductRouteArgs = ProductRouteArgs(
            productId = product.id,
            name = product.name,
            price = product.price,
            description = product.description,
            isAvailable = product.isAvailable
        )
    }
}

sealed interface AppRoute {
    data object Splash : AppRoute
    data object Onboarding : AppRoute
    data object Login : AppRoute
    data object Register : AppRoute
    data object ForgotPassword : AppRoute

    // Customer routes
    data object CustomerHome : AppRoute
    data object CustomerProductList : AppRoute
    data class CustomerProductDetail(val product: ProductRouteArgs) : AppRoute
    data object CustomerCart : AppRoute
    data object CustomerCheckout : AppRoute
    data object CustomerOrderSummary : AppRoute
    data object CustomerOrderList : AppRoute
    data class CustomerOrderDetail(val orderId: String) : AppRoute
    data class CustomerOrderTracking(val orderId: String) : AppRoute
    data class CustomerPaymentSelection(val orderId: String) : AppRoute
    data class CustomerQrisPayment(val orderId: String) : AppRoute
    data class CustomerCashPayment(val orderId: String) : AppRoute
    data object CustomerProfile : AppRoute
    data object CustomerEditProfile : AppRoute

    // Owner routes
    data object OwnerHome : AppRoute
    data class OwnerOrderList(val status: String? = null) : AppRoute
    data class OwnerOrderDetail(val orderId: String) : AppRoute
    data class OwnerQrisVerification(val orderId: String) : AppRoute
    data object OwnerQrisSettings : AppRoute
    data object OwnerProducts : AppRoute
    data object OwnerProductCreate : AppRoute
    data class OwnerProductEdit(val productId: String) : AppRoute
    data object OwnerProfile : AppRoute

    // Courier routes
    data object CourierHome : AppRoute
    data class CourierOrderList(val status: String? = null) : AppRoute
    data class CourierOrderDetail(val orderId: String) : AppRoute
    data class CourierDeliveryActive(val orderId: String) : AppRoute
    data class CourierCashConfirmation(val orderId: String) : AppRoute
    data object CourierProfile : AppRoute

    // Shared routes
    data class WebView(val url: String, val title: String) : AppRoute
    data object Settings : AppRoute
    data object Notifications : AppRoute
}

object RoutePaths {
    const val ARG_ORDER_ID = "orderId"
    const val ARG_PRODUCT_ID = "productId"
    const val ARG_STATUS = "status"
    const val ARG_URL = "url"
    const val ARG_TITLE = "title"

    /**
     * Detail produk memakai argumen query, bukan request baru, karena
     * specification tidak menyediakan `GET /products/{id}`.
     */
    const val ARG_PRODUCT_NAME = "productName"
    const val ARG_PRODUCT_PRICE = "productPrice"
    const val ARG_PRODUCT_DESCRIPTION = "productDescription"
    const val ARG_PRODUCT_IS_AVAILABLE = "productIsAvailable"

    const val SPLASH = "splash"
    const val ONBOARDING = "onboarding"
    const val LOGIN = "login"
    const val REGISTER = "register"
    const val FORGOT_PASSWORD = "forgot_password"

    const val CUSTOMER_HOME = "customer_home"
    const val CUSTOMER_PRODUCT_LIST = "customer_products"
    const val CUSTOMER_PRODUCT_DETAIL =
        "customer_product_detail/{productId}?productName={productName}&productPrice={productPrice}&productDescription={productDescription}&productIsAvailable={productIsAvailable}"
    const val CUSTOMER_CART = "customer_cart"
    const val CUSTOMER_CHECKOUT = "customer_checkout"
    const val CUSTOMER_ORDER_SUMMARY = "customer_order_summary"
    const val CUSTOMER_ORDER_LIST = "customer_orders"
    const val CUSTOMER_ORDER_DETAIL = "customer_orders/{orderId}"
    const val CUSTOMER_ORDER_TRACKING = "customer_orders/{orderId}/tracking"
const val CUSTOMER_PAYMENT_SELECTION = "customer_orders/{orderId}/payment"
const val CUSTOMER_QRIS_PAYMENT = "customer_orders/{orderId}/payment/qris"
const val CUSTOMER_CASH_PAYMENT = "customer_orders/{orderId}/payment/cash"
    const val CUSTOMER_PROFILE = "customer_profile"
    const val CUSTOMER_EDIT_PROFILE = "customer_profile/edit"

    const val OWNER_HOME = "owner_home"
    const val OWNER_ORDER_LIST = "owner_orders?status={status}"
    const val OWNER_ORDER_DETAIL = "owner_orders/{orderId}"
    const val OWNER_QRIS_VERIFICATION = "owner_orders/{orderId}/qris_verification"
    const val OWNER_QRIS_SETTINGS = "owner/qris_settings"
    const val OWNER_PRODUCTS = "owner/products"
    const val OWNER_PRODUCT_CREATE = "owner/products/create"
    const val OWNER_PRODUCT_EDIT = "owner/products/{productId}/edit"
    const val OWNER_PROFILE = "owner_profile"

    const val COURIER_HOME = "courier_home"
    const val COURIER_ORDER_LIST = "courier_orders?status={status}"
    const val COURIER_ORDER_DETAIL = "courier_orders/{orderId}"
    const val COURIER_DELIVERY_ACTIVE = "courier_orders/{orderId}/delivery"
    const val COURIER_CASH_CONFIRMATION = "courier_orders/{orderId}/cash_confirmation"
    const val COURIER_PROFILE = "courier_profile"

    const val WEBVIEW = "webview?url={url}&title={title}"
    const val SETTINGS = "settings"
    const val NOTIFICATIONS = "notifications"
}

fun AppRoute.toRouteString(): String = when (this) {
    AppRoute.Splash -> RoutePaths.SPLASH
    AppRoute.Onboarding -> RoutePaths.ONBOARDING
    AppRoute.Login -> RoutePaths.LOGIN
    AppRoute.Register -> RoutePaths.REGISTER
    AppRoute.ForgotPassword -> RoutePaths.FORGOT_PASSWORD
    AppRoute.CustomerHome -> RoutePaths.CUSTOMER_HOME
    AppRoute.CustomerProductList -> RoutePaths.CUSTOMER_PRODUCT_LIST
    is AppRoute.CustomerProductDetail -> RoutePaths.CUSTOMER_PRODUCT_DETAIL
        .replace("{${RoutePaths.ARG_PRODUCT_ID}}", Uri.encode(product.productId))
        .replace("{${RoutePaths.ARG_PRODUCT_NAME}}", Uri.encode(product.name))
        .replace("{${RoutePaths.ARG_PRODUCT_PRICE}}", Uri.encode(product.price))
        .replace(
            "{${RoutePaths.ARG_PRODUCT_DESCRIPTION}}",
            Uri.encode(product.description.orEmpty())
        )
        .replace(
            "{${RoutePaths.ARG_PRODUCT_IS_AVAILABLE}}",
            product.isAvailable.toString()
        )
    AppRoute.CustomerCart -> RoutePaths.CUSTOMER_CART
    AppRoute.CustomerCheckout -> RoutePaths.CUSTOMER_CHECKOUT
    AppRoute.CustomerOrderSummary -> RoutePaths.CUSTOMER_ORDER_SUMMARY
    AppRoute.CustomerOrderList -> RoutePaths.CUSTOMER_ORDER_LIST
    is AppRoute.CustomerOrderDetail ->
        RoutePaths.CUSTOMER_ORDER_DETAIL.replace("{${RoutePaths.ARG_ORDER_ID}}", orderId)
    is AppRoute.CustomerOrderTracking ->
        RoutePaths.CUSTOMER_ORDER_TRACKING.replace("{${RoutePaths.ARG_ORDER_ID}}", orderId)
    is AppRoute.CustomerPaymentSelection ->
        RoutePaths.CUSTOMER_PAYMENT_SELECTION.replace("{${RoutePaths.ARG_ORDER_ID}}", orderId)
is AppRoute.CustomerQrisPayment ->
    RoutePaths.CUSTOMER_QRIS_PAYMENT.replace("{${RoutePaths.ARG_ORDER_ID}}", orderId)
    is AppRoute.CustomerCashPayment ->
    RoutePaths.CUSTOMER_CASH_PAYMENT.replace("{${RoutePaths.ARG_ORDER_ID}}", orderId)
    AppRoute.CustomerProfile -> RoutePaths.CUSTOMER_PROFILE
    AppRoute.CustomerEditProfile -> RoutePaths.CUSTOMER_EDIT_PROFILE
    AppRoute.OwnerHome -> RoutePaths.OWNER_HOME
    is AppRoute.OwnerOrderList -> if (status.isNullOrBlank()) {
        RoutePaths.OWNER_ORDER_LIST.substringBefore('?')
    } else {
        RoutePaths.OWNER_ORDER_LIST.replace("{${RoutePaths.ARG_STATUS}}", Uri.encode(status))
    }
    is AppRoute.OwnerOrderDetail ->
        RoutePaths.OWNER_ORDER_DETAIL.replace("{${RoutePaths.ARG_ORDER_ID}}", orderId)
    is AppRoute.OwnerQrisVerification ->
        RoutePaths.OWNER_QRIS_VERIFICATION.replace("{${RoutePaths.ARG_ORDER_ID}}", orderId)
    AppRoute.OwnerQrisSettings -> RoutePaths.OWNER_QRIS_SETTINGS
    AppRoute.OwnerProducts -> RoutePaths.OWNER_PRODUCTS
    AppRoute.OwnerProductCreate -> RoutePaths.OWNER_PRODUCT_CREATE
    is AppRoute.OwnerProductEdit ->
        RoutePaths.OWNER_PRODUCT_EDIT.replace("{${RoutePaths.ARG_PRODUCT_ID}}", productId)
    AppRoute.OwnerProfile -> RoutePaths.OWNER_PROFILE
    AppRoute.CourierHome -> RoutePaths.COURIER_HOME
    is AppRoute.CourierOrderList -> if (status.isNullOrBlank()) {
        RoutePaths.COURIER_ORDER_LIST.substringBefore('?')
    } else {
        RoutePaths.COURIER_ORDER_LIST.replace("{${RoutePaths.ARG_STATUS}}", Uri.encode(status))
    }
    is AppRoute.CourierOrderDetail ->
        RoutePaths.COURIER_ORDER_DETAIL.replace("{${RoutePaths.ARG_ORDER_ID}}", orderId)
    is AppRoute.CourierDeliveryActive ->
        RoutePaths.COURIER_DELIVERY_ACTIVE.replace("{${RoutePaths.ARG_ORDER_ID}}", orderId)
    is AppRoute.CourierCashConfirmation ->
        RoutePaths.COURIER_CASH_CONFIRMATION.replace("{${RoutePaths.ARG_ORDER_ID}}", orderId)
    AppRoute.CourierProfile -> RoutePaths.COURIER_PROFILE
    is AppRoute.WebView -> RoutePaths.WEBVIEW
        .replace("{${RoutePaths.ARG_URL}}", Uri.encode(url))
        .replace("{${RoutePaths.ARG_TITLE}}", Uri.encode(title))
    AppRoute.Settings -> RoutePaths.SETTINGS
    AppRoute.Notifications -> RoutePaths.NOTIFICATIONS
}
