package com.kyusui.app.ui.navigation

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.NavBackStackEntry
import androidx.navigation.NavDestination.Companion.hierarchy
import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import androidx.hilt.navigation.compose.hiltViewModel
import com.kyusui.app.data.api.ImageLoaderEntryPoint
import com.kyusui.app.domain.model.PaymentMethod
import com.kyusui.app.ui.auth.AuthEntryPoint
import com.kyusui.app.ui.auth.AuthUiState
import com.kyusui.app.ui.auth.AuthViewModel
import com.kyusui.app.ui.auth.LoginScreen
import com.kyusui.app.ui.auth.RegisterScreen
import com.kyusui.app.ui.auth.SplashScreen
import com.kyusui.app.ui.components.ProvideSnackbarManager
import com.kyusui.app.ui.customer.createorder.CartViewModel
import com.kyusui.app.ui.customer.createorder.CreateOrderScreen
import com.kyusui.app.ui.customer.createorder.CreateOrderViewModel
import com.kyusui.app.ui.customer.createorder.LocationStatus
import com.kyusui.app.ui.customer.createorder.readLastKnownLocation
import com.kyusui.app.ui.customer.createorder.rememberLocationRequestState
import com.kyusui.app.ui.customer.home.CustomerHomeScreen
import com.kyusui.app.ui.customer.orders.OrderDetailScreen
import com.kyusui.app.ui.customer.orders.OrderDetailViewModel
import com.kyusui.app.ui.customer.orders.OrderHistoryScreen
import com.kyusui.app.ui.customer.orders.OrderHistoryViewModel
import com.kyusui.app.ui.customer.orders.OrderSummaryScreen
import com.kyusui.app.ui.customer.payment.CashPaymentScreen
import com.kyusui.app.ui.customer.payment.PaymentMethodSelectionScreen
import com.kyusui.app.ui.customer.payment.PaymentViewModel
import com.kyusui.app.ui.customer.payment.QrisPaymentScreen
import com.kyusui.app.ui.customer.products.ProductDetailScreen
import com.kyusui.app.ui.customer.products.ProductDetailUiState
import com.kyusui.app.ui.customer.products.ProductDetailViewModel
import com.kyusui.app.ui.customer.products.ProductListScreen
import com.kyusui.app.ui.customer.products.ProductListViewModel
import com.kyusui.app.ui.state.SubmitState
import androidx.compose.runtime.remember
import androidx.compose.ui.platform.LocalContext
import dagger.hilt.android.EntryPointAccessors

private fun NavBackStackEntry.argument(key: String): String? = arguments?.getString(key)

@Composable
fun AppNavHost(
    startRoute: String = RoutePaths.SPLASH,
    modifier: Modifier = Modifier,
    navController: NavHostController = rememberNavController()
) {
    // Satu AuthViewModel untuk seluruh flow auth, di-scope ke host activity
    // supaya splash, login, dan register berbagi state yang sama.
    val authViewModel: AuthViewModel = hiltViewModel()

    ProvideSnackbarManager {
        Box(modifier = modifier.fillMaxSize()) {
            NavHost(
                navController = navController,
                startDestination = startRoute,
                modifier = Modifier.fillMaxSize()
            ) {
                authGraph(navController, authViewModel)
                customerGraph(navController, authViewModel)
                ownerGraph(navController)
                courierGraph(navController)
                sharedGraph(navController)
            }
        }
    }
}

private fun NavGraphBuilder.authGraph(
    navController: NavHostController,
    authViewModel: AuthViewModel
) {
    composable(RoutePaths.SPLASH) {
        SplashScreen(
            viewModel = authViewModel,
            onEntryPointResolved = { entryPoint ->
                navController.navigate(entryPoint.toRouteString()) {
                    popUpTo(RoutePaths.SPLASH) { inclusive = true }
                    launchSingleTop = true
                }
            }
        )
    }

    composable(RoutePaths.LOGIN) {
        LoginScreen(
            viewModel = authViewModel,
            onRegisterClick = {
                navController.navigate(RoutePaths.REGISTER) { launchSingleTop = true }
            }
        )

        // LoginScreen hanya menampilkan state; perpindahan layar setelah login
        // sukses ditangani di sini supaya role routing berlaku untuk semua form.
        RoleRoutingEffect(authViewModel, navController)
    }

    composable(RoutePaths.REGISTER) {
        RegisterScreen(
            viewModel = authViewModel,
            onLoginClick = { navController.popBackStack() }
        )

        RoleRoutingEffect(authViewModel, navController)
    }

    composable(RoutePaths.ONBOARDING) {
        PlaceholderScreen(title = "Onboarding", subtitle = "Onboarding")
    }

    composable(RoutePaths.FORGOT_PASSWORD) {
        PlaceholderScreen(title = "Lupa Password", subtitle = "Forgot Password")
    }
}

/**
 * Memindahkan navigasi ke home sesuai role begitu login atau register sukses.
 *
 * `SplashScreen` sudah melakukan perpindahan yang sama saat session dipulihkan,
 * jadi effect ini hanya menutup jalur login dan register.
 */
@Composable
private fun RoleRoutingEffect(
    authViewModel: AuthViewModel,
    navController: NavHostController
) {
    val uiState by authViewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(uiState) {
        val user = (uiState as? AuthUiState.Authenticated)?.user ?: return@LaunchedEffect
        val route = AuthEntryPoint.fromRole(user.role).toRouteString()
        if (navController.currentDestination?.route == route) return@LaunchedEffect

        navController.navigate(route) {
            popUpTo(RoutePaths.LOGIN) { inclusive = true }
            popUpTo(RoutePaths.REGISTER) { inclusive = true }
            launchSingleTop = true
        }
    }
}

private fun AuthEntryPoint.toRouteString(): String = when (this) {
    AuthEntryPoint.CUSTOMER_HOME -> RoutePaths.CUSTOMER_HOME
    AuthEntryPoint.OWNER_HOME -> RoutePaths.OWNER_HOME
    AuthEntryPoint.COURIER_HOME -> RoutePaths.COURIER_HOME
    AuthEntryPoint.LOGIN -> RoutePaths.LOGIN
}


/**
 * Entry back stack yang dipakai bersama oleh semua layar dalam alur order.
 *
 * `hiltViewModel()` tanpa owner akan mengikat ViewModel ke entry layar itu
 * sendiri, sehingga keranjang yang diisi di daftar produk akan terlihat kosong
 * lagi di form dan ringkasan. Dengan di-scope ke entry `CUSTOMER_HOME`, semua
 * destination membaca instance yang sama. Logout menghapus `CUSTOMER_HOME`
 * secara inclusive, sehingga state ikut tercemar tanpa perlu pembersih manual.
 */
private fun NavHostController.sharedCustomerOwner(): NavBackStackEntry {
    val hasCustomerHome = currentBackStackEntry?.destination?.hierarchy
        ?.any { it.route == RoutePaths.CUSTOMER_HOME } == true
    return if (hasCustomerHome) {
        getBackStackEntry(RoutePaths.CUSTOMER_HOME)
    } else {
        // Hanya terjadi bila app dibuka langsung ke destination customer
        // (deep link) tanpa melewati home. Memakai entry saat ini tetap
        // memberi ViewModel yang valid, hanya tidak dibagi ke layar lain.
        requireNotNull(currentBackStackEntry)
    }
}

@Composable
private fun NavHostController.sharedCartViewModel(): CartViewModel =
    hiltViewModel(sharedCustomerOwner())

@Composable
private fun NavHostController.sharedCreateOrderViewModel(): CreateOrderViewModel =
    hiltViewModel(sharedCustomerOwner())

/**
 * Menjalankan navigasi ke detail order setelah order dibuat.
 *
 * Dipisah dari tiap layar karena `navigateToOrderId` dimiliki ViewModel yang
 * dibagi; layar mana pun yang merender efek ini akan bereaksi atas hasil yang
 * sama, dan `consumeNavigation()` mencegah perpindahan ganda.
 */
@Composable
private fun OrderSubmitNavigationEffect(
    viewModel: CreateOrderViewModel,
    onOrderCreated: (String) -> Unit
) {
    val navigateToOrderId by viewModel.navigateToOrderId.collectAsStateWithLifecycle()

    LaunchedEffect(navigateToOrderId) {
        val orderId = navigateToOrderId ?: return@LaunchedEffect
        viewModel.consumeNavigation()
        onOrderCreated(orderId)
    }
}

private fun NavGraphBuilder.customerGraph(
    navController: NavHostController,
    authViewModel: AuthViewModel
) {
    composable(RoutePaths.CUSTOMER_HOME) {
        val authState by authViewModel.uiState.collectAsStateWithLifecycle()
        CustomerHomeScreen(
            customerName = (authState as? AuthUiState.Authenticated)?.user?.name,
            onBrowseProducts = {
                navController.navigate(RoutePaths.CUSTOMER_PRODUCT_LIST)
            },
            onViewActiveOrders = {
                navController.navigate(RoutePaths.CUSTOMER_ORDER_LIST)
            },
            onViewHistory = {
                navController.navigate(RoutePaths.CUSTOMER_ORDER_LIST)
            },
            onLogout = {
                authViewModel.logout()
                navController.navigate(RoutePaths.LOGIN) {
                    popUpTo(RoutePaths.CUSTOMER_HOME) { inclusive = true }
                    launchSingleTop = true
                }
            }
        )
    }

    composable(RoutePaths.CUSTOMER_PRODUCT_LIST) {
        val productListViewModel: ProductListViewModel = hiltViewModel()
        val cartViewModel = navController.sharedCartViewModel()
        val uiState by productListViewModel.uiState.collectAsStateWithLifecycle()
        val cartState by cartViewModel.uiState.collectAsStateWithLifecycle()

        ProductListScreen(
            uiState = uiState,
            cartCount = cartState.totalQuantity,
            onBack = { navController.popBackStack() },
            onRetry = productListViewModel::loadProducts,
            onLoadMore = productListViewModel::loadNextPage,
            onGoToCart = {
                navController.navigate(RoutePaths.CUSTOMER_CART)
            },
            onProductClick = { product ->
                navController.navigate(
                    AppRoute.CustomerProductDetail(ProductRouteArgs.from(product)).toRouteString()
                )
            },
            onAddToCart = cartViewModel::addProduct,
            onChangeQuantity = { product, delta ->
                val current = cartViewModel.uiState.value.lines
                    .firstOrNull { it.product.id == product.id }?.quantity ?: 0
                cartViewModel.setQuantity(product.id, current + delta)
            }
        )
    }

    composable(
        route = RoutePaths.CUSTOMER_PRODUCT_DETAIL,
        arguments = listOf(
            navArgument(RoutePaths.ARG_PRODUCT_ID) { type = NavType.StringType },
            navArgument(RoutePaths.ARG_PRODUCT_NAME) { type = NavType.StringType },
            navArgument(RoutePaths.ARG_PRODUCT_PRICE) { type = NavType.StringType },
            navArgument(RoutePaths.ARG_PRODUCT_DESCRIPTION) {
                type = NavType.StringType
                defaultValue = ""
            },
            navArgument(RoutePaths.ARG_PRODUCT_IS_AVAILABLE) { type = NavType.BoolType }
        )
    ) {
        val productDetailViewModel: ProductDetailViewModel = hiltViewModel()
        val cartViewModel = navController.sharedCartViewModel()
        val uiState by productDetailViewModel.uiState.collectAsStateWithLifecycle()
        val cartState by cartViewModel.uiState.collectAsStateWithLifecycle()

        ProductDetailScreen(
            uiState = uiState,
            inCartQuantity = cartState.lines
                .firstOrNull { it.product.id == (uiState as? ProductDetailUiState.Ready)?.product?.id }
                ?.quantity
                ?: 0,
            onBack = { navController.popBackStack() },
            onAddToCart = cartViewModel::addProduct
        )
    }

    composable(RoutePaths.CUSTOMER_CART) {
        val cartViewModel = navController.sharedCartViewModel()
        val createOrderViewModel = navController.sharedCreateOrderViewModel()
        val cartState by cartViewModel.uiState.collectAsStateWithLifecycle()
        val createOrderState by createOrderViewModel.uiState.collectAsStateWithLifecycle()
        val context = LocalContext.current

        // Status "Locating" sudah diset sebelum meminta izin, jadi jalur baca
        // lokasi ini dipakai lagi dari callback "izin diberikan" tanpa perlu
        // tap kedua oleh customer.
        val readLocation = {
            val point = readLastKnownLocation(context)
            if (point != null) {
                cartViewModel.updateLocation(point)
            } else {
                cartViewModel.updateLocationStatus(LocationStatus.Unavailable)
            }
        }

        val locationRequest = rememberLocationRequestState(
            onPermissionDenied = {
                cartViewModel.updateLocationStatus(LocationStatus.PermissionDenied)
            },
            onPermissionGranted = { readLocation() }
        )

        val requestLocation = {
            cartViewModel.updateLocationStatus(LocationStatus.Locating)
            if (locationRequest.isGranted(context)) {
                readLocation()
            } else {
                locationRequest.launcher()
            }
        }

        CreateOrderScreen(
            cartState = cartState,
            submitState = createOrderState.submitState,
            onBack = { navController.popBackStack() },
            onAddressChange = cartViewModel::updateAddress,
            onRequestLocation = requestLocation,
            onContinueToSummary = {
                navController.navigate(RoutePaths.CUSTOMER_ORDER_SUMMARY)
            },
            onSubmit = { createOrderViewModel.submitOrder(cartState) }
        )
    }

    composable(RoutePaths.CUSTOMER_ORDER_SUMMARY) {
        val cartViewModel = navController.sharedCartViewModel()
        val createOrderViewModel = navController.sharedCreateOrderViewModel()
        val cartState by cartViewModel.uiState.collectAsStateWithLifecycle()
        val createOrderState by createOrderViewModel.uiState.collectAsStateWithLifecycle()

        OrderSubmitNavigationEffect(
            viewModel = createOrderViewModel,
            onOrderCreated = { orderId ->
                // Keranjang dikosongkan hanya setelah order benar-benar dibuat,
                // supaya gagal submit tidak menghilangkan isian customer.
                cartViewModel.clear()
                createOrderViewModel.clearSubmitState()
                navController.navigate(RoutePaths.CUSTOMER_ORDER_DETAIL.replaceOrderId(orderId)) {
                    popUpTo(RoutePaths.CUSTOMER_HOME)
                }
            }
        )

        OrderSummaryScreen(
            cartState = cartState,
            isSubmitting = createOrderState.isSubmitting,
            onBack = { navController.popBackStack() },
            onSubmit = { createOrderViewModel.submitOrder(cartState) }
        )
    }

    composable(RoutePaths.CUSTOMER_ORDER_LIST) {
        val historyViewModel: OrderHistoryViewModel = hiltViewModel()
        val uiState by historyViewModel.uiState.collectAsStateWithLifecycle()

        OrderHistoryScreen(
            uiState = uiState,
            onBack = { navController.popBackStack() },
            onRetry = historyViewModel::loadOrders,
            onLoadMore = historyViewModel::loadNextPage,
            onFilterChange = historyViewModel::setStatusFilter,
            onOrderClick = { order ->
                navController.navigate(
                    AppRoute.CustomerOrderDetail(order.id).toRouteString()
                )
            }
        )
    }

    composable(
        route = RoutePaths.CUSTOMER_ORDER_DETAIL,
        arguments = listOf(navArgument(RoutePaths.ARG_ORDER_ID) { type = NavType.StringType })
    ) {
        val orderDetailViewModel: OrderDetailViewModel = hiltViewModel()
        val uiState by orderDetailViewModel.uiState.collectAsStateWithLifecycle()

        OrderDetailScreen(
            uiState = uiState,
            onBack = { navController.popBackStack() },
            onRetry = orderDetailViewModel::loadOrder,
            onPay = { orderId ->
                navController.navigate(RoutePaths.CUSTOMER_PAYMENT_SELECTION.replaceOrderId(orderId))
            }
        )
    }

    composable(
        route = RoutePaths.CUSTOMER_ORDER_TRACKING,
        arguments = listOf(navArgument(RoutePaths.ARG_ORDER_ID) { type = NavType.StringType })
    ) { entry ->
        PlaceholderScreen(
            title = "Lacak Pesanan",
            subtitle = entry.argument(RoutePaths.ARG_ORDER_ID).orEmpty()
        )
    }
    composable(
        route = RoutePaths.CUSTOMER_PAYMENT_SELECTION,
        arguments = listOf(navArgument(RoutePaths.ARG_ORDER_ID) { type = NavType.StringType })
    ) {
        val paymentViewModel: PaymentViewModel = hiltViewModel()
        val uiState by paymentViewModel.uiState.collectAsStateWithLifecycle()

        // Navigasi hanya setelah backend mengonfirmasi method tersimpan.
        // Memindah layar saat request masih berjalan akan membuka layar
        // pembayaran untuk payment yang belum tentu ada.
        LaunchedEffect(uiState.selectState) {
            if (uiState.selectState is SubmitState.Success) {
                val route = when (uiState.method) {
                    PaymentMethod.QRIS -> RoutePaths.CUSTOMER_QRIS_PAYMENT
                    PaymentMethod.CASH -> RoutePaths.CUSTOMER_CASH_PAYMENT
                    // Method tidak dikenal tidak boleh membuka layar pembayaran.
                    null -> return@LaunchedEffect
                }
                paymentViewModel.clearSubmitStates()
                navController.navigate(route.replaceOrderId(uiState.orderId)) {
                    popUpTo(RoutePaths.CUSTOMER_ORDER_DETAIL)
                }
            }
        }

        PaymentMethodSelectionScreen(
            uiState = uiState,
            onBack = { navController.popBackStack() },
            onSelect = paymentViewModel::selectMethod,
            onRetryPayment = paymentViewModel::refreshPayment
        )
    }
    composable(
        route = RoutePaths.CUSTOMER_QRIS_PAYMENT,
        arguments = listOf(navArgument(RoutePaths.ARG_ORDER_ID) { type = NavType.StringType })
    ) {
        val paymentViewModel: PaymentViewModel = hiltViewModel()
        val uiState by paymentViewModel.uiState.collectAsStateWithLifecycle()
        val context = LocalContext.current
        // QRIS image adalah aset privat toko dan dilayani lewat endpoint ber-token.
        // ImageLoader yang di-taking dari Hilt memakai OkHttpClient yang sama
        // dengan Retrofit, jadi header Authorization ikut terbawa.
        val imageLoader = remember(context) {
            EntryPointAccessors
                .fromApplication(context, ImageLoaderEntryPoint::class.java)
                .imageLoader()
        }

        QrisPaymentScreen(
            uiState = uiState,
            onBack = { navController.popBackStack() },
            onRefresh = paymentViewModel::refreshPayment,
            onRetryQris = paymentViewModel::loadActiveQris,
            onProofPicked = paymentViewModel::onProofPicked,
            onClearProof = paymentViewModel::clearProofSelection,
            onUploadProof = { paymentViewModel.uploadProof() },
            imageLoader = imageLoader
        )
    }
    composable(
        route = RoutePaths.CUSTOMER_CASH_PAYMENT,
        arguments = listOf(navArgument(RoutePaths.ARG_ORDER_ID) { type = NavType.StringType })
    ) {
        val paymentViewModel: PaymentViewModel = hiltViewModel()
        val uiState by paymentViewModel.uiState.collectAsStateWithLifecycle()

        CashPaymentScreen(
            uiState = uiState,
            onBack = { navController.popBackStack() },
            onRefresh = paymentViewModel::refreshPayment
        )
    }
    composable(RoutePaths.CUSTOMER_PROFILE) {
        PlaceholderScreen(title = "Profil", subtitle = "Customer Profile")
    }
    composable(RoutePaths.CUSTOMER_EDIT_PROFILE) {
        PlaceholderScreen(title = "Edit Profil", subtitle = "Edit Profile")
    }
}

private fun String.replaceOrderId(orderId: String): String =
    replace("{${RoutePaths.ARG_ORDER_ID}}", orderId)

private fun NavGraphBuilder.ownerGraph(navController: NavHostController) {
    composable(RoutePaths.OWNER_HOME) {
        PlaceholderScreen(title = "Beranda Owner", subtitle = "Owner Home")
    }
    composable(
        route = RoutePaths.OWNER_ORDER_LIST,
        arguments = listOf(
            navArgument(RoutePaths.ARG_STATUS) {
                type = NavType.StringType
                nullable = true
                defaultValue = null
            }
        )
    ) { entry ->
        PlaceholderScreen(
            title = "Pesanan Owner",
            subtitle = entry.argument(RoutePaths.ARG_STATUS) ?: "Semua"
        )
    }
    composable(
        route = RoutePaths.OWNER_ORDER_DETAIL,
        arguments = listOf(navArgument(RoutePaths.ARG_ORDER_ID) { type = NavType.StringType })
    ) { entry ->
        PlaceholderScreen(
            title = "Detail Pesanan",
            subtitle = entry.argument(RoutePaths.ARG_ORDER_ID).orEmpty()
        )
    }
    composable(
        route = RoutePaths.OWNER_QRIS_VERIFICATION,
        arguments = listOf(navArgument(RoutePaths.ARG_ORDER_ID) { type = NavType.StringType })
    ) { entry ->
        PlaceholderScreen(
            title = "Verifikasi QRIS",
            subtitle = entry.argument(RoutePaths.ARG_ORDER_ID).orEmpty()
        )
    }
    composable(RoutePaths.OWNER_QRIS_SETTINGS) {
        PlaceholderScreen(title = "Pengaturan QRIS", subtitle = "QRIS Settings")
    }
    composable(RoutePaths.OWNER_PRODUCTS) {
        PlaceholderScreen(title = "Produk", subtitle = "Owner Products")
    }
    composable(RoutePaths.OWNER_PRODUCT_CREATE) {
        PlaceholderScreen(title = "Tambah Produk", subtitle = "Create Product")
    }
    composable(
        route = RoutePaths.OWNER_PRODUCT_EDIT,
        arguments = listOf(navArgument(RoutePaths.ARG_PRODUCT_ID) { type = NavType.StringType })
    ) { entry ->
        PlaceholderScreen(
            title = "Edit Produk",
            subtitle = entry.argument(RoutePaths.ARG_PRODUCT_ID).orEmpty()
        )
    }
    composable(RoutePaths.OWNER_PROFILE) {
        PlaceholderScreen(title = "Profil Owner", subtitle = "Owner Profile")
    }
}

private fun NavGraphBuilder.courierGraph(navController: NavHostController) {
    composable(RoutePaths.COURIER_HOME) {
        PlaceholderScreen(title = "Beranda Kurir", subtitle = "Courier Home")
    }
    composable(
        route = RoutePaths.COURIER_ORDER_LIST,
        arguments = listOf(
            navArgument(RoutePaths.ARG_STATUS) {
                type = NavType.StringType
                nullable = true
                defaultValue = null
            }
        )
    ) { entry ->
        PlaceholderScreen(
            title = "Pesanan Kurir",
            subtitle = entry.argument(RoutePaths.ARG_STATUS) ?: "Semua"
        )
    }
    composable(
        route = RoutePaths.COURIER_ORDER_DETAIL,
        arguments = listOf(navArgument(RoutePaths.ARG_ORDER_ID) { type = NavType.StringType })
    ) { entry ->
        PlaceholderScreen(
            title = "Detail Pesanan",
            subtitle = entry.argument(RoutePaths.ARG_ORDER_ID).orEmpty()
        )
    }
    composable(
        route = RoutePaths.COURIER_DELIVERY_ACTIVE,
        arguments = listOf(navArgument(RoutePaths.ARG_ORDER_ID) { type = NavType.StringType })
    ) { entry ->
        PlaceholderScreen(
            title = "Pengiriman Aktif",
            subtitle = entry.argument(RoutePaths.ARG_ORDER_ID).orEmpty()
        )
    }
    composable(
        route = RoutePaths.COURIER_CASH_CONFIRMATION,
        arguments = listOf(navArgument(RoutePaths.ARG_ORDER_ID) { type = NavType.StringType })
    ) { entry ->
        PlaceholderScreen(
            title = "Konfirmasi Tunai",
            subtitle = entry.argument(RoutePaths.ARG_ORDER_ID).orEmpty()
        )
    }
    composable(RoutePaths.COURIER_PROFILE) {
        PlaceholderScreen(title = "Profil Kurir", subtitle = "Courier Profile")
    }
}

private fun NavGraphBuilder.sharedGraph(navController: NavHostController) {
    composable(
        route = RoutePaths.WEBVIEW,
        arguments = listOf(
            navArgument(RoutePaths.ARG_URL) { type = NavType.StringType },
            navArgument(RoutePaths.ARG_TITLE) { type = NavType.StringType }
        )
    ) { entry ->
        PlaceholderScreen(
            title = entry.argument(RoutePaths.ARG_TITLE).orEmpty(),
            subtitle = entry.argument(RoutePaths.ARG_URL).orEmpty()
        )
    }
    composable(RoutePaths.SETTINGS) {
        PlaceholderScreen(title = "Pengaturan", subtitle = "Settings")
    }
    composable(RoutePaths.NOTIFICATIONS) {
        PlaceholderScreen(title = "Notifikasi", subtitle = "Notifications")
    }
}

@Composable
private fun PlaceholderScreen(title: String, subtitle: String? = null) {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        androidx.compose.foundation.layout.Column(
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(text = title, style = MaterialTheme.typography.displayMedium)
            if (!subtitle.isNullOrBlank()) {
                Text(
                    text = subtitle,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
    }
}
