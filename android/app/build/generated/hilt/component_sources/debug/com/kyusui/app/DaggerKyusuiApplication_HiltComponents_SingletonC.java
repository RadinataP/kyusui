package com.kyusui.app;

import android.app.Activity;
import android.app.Service;
import android.content.ContentResolver;
import android.view.View;
import androidx.datastore.core.DataStore;
import androidx.datastore.preferences.core.Preferences;
import androidx.fragment.app.Fragment;
import androidx.lifecycle.SavedStateHandle;
import androidx.lifecycle.ViewModel;
import coil.ImageLoader;
import com.kyusui.app.data.api.ApiErrorParser;
import com.kyusui.app.data.api.AuthApi;
import com.kyusui.app.data.api.AuthInterceptor;
import com.kyusui.app.data.api.AuthTokenStore;
import com.kyusui.app.data.api.CustomerApi;
import com.kyusui.app.data.api.RetrofitModule;
import com.kyusui.app.data.api.RetrofitModule_ProvideApiErrorParserFactory;
import com.kyusui.app.data.api.RetrofitModule_ProvideAuthApiFactory;
import com.kyusui.app.data.api.RetrofitModule_ProvideAuthInterceptorFactory;
import com.kyusui.app.data.api.RetrofitModule_ProvideAuthTokenStoreFactory;
import com.kyusui.app.data.api.RetrofitModule_ProvideCustomerApiFactory;
import com.kyusui.app.data.api.RetrofitModule_ProvideImageLoaderFactory;
import com.kyusui.app.data.api.RetrofitModule_ProvideJsonFactory;
import com.kyusui.app.data.api.RetrofitModule_ProvideOkHttpClientFactory;
import com.kyusui.app.data.api.RetrofitModule_ProvideRetrofitFactory;
import com.kyusui.app.data.datastore.DataStoreProviderModule;
import com.kyusui.app.data.datastore.DataStoreProviderModule_ProvidePreferencesDataStoreFactory;
import com.kyusui.app.data.datastore.PreferencesRepository;
import com.kyusui.app.data.repository.AuthRepositoryImpl;
import com.kyusui.app.data.repository.CustomerRepositoryImpl;
import com.kyusui.app.data.upload.ContentResolverProofFileSource;
import com.kyusui.app.di.ContentModule;
import com.kyusui.app.di.ContentModule_ProvideContentResolverFactory;
import com.kyusui.app.ui.auth.AuthViewModel;
import com.kyusui.app.ui.auth.AuthViewModel_HiltModules_KeyModule_ProvideFactory;
import com.kyusui.app.ui.customer.createorder.CartViewModel;
import com.kyusui.app.ui.customer.createorder.CartViewModel_HiltModules_KeyModule_ProvideFactory;
import com.kyusui.app.ui.customer.createorder.CreateOrderViewModel;
import com.kyusui.app.ui.customer.createorder.CreateOrderViewModel_HiltModules_KeyModule_ProvideFactory;
import com.kyusui.app.ui.customer.orders.OrderDetailViewModel;
import com.kyusui.app.ui.customer.orders.OrderDetailViewModel_HiltModules_KeyModule_ProvideFactory;
import com.kyusui.app.ui.customer.orders.OrderHistoryViewModel;
import com.kyusui.app.ui.customer.orders.OrderHistoryViewModel_HiltModules_KeyModule_ProvideFactory;
import com.kyusui.app.ui.customer.payment.PaymentViewModel;
import com.kyusui.app.ui.customer.payment.PaymentViewModel_HiltModules_KeyModule_ProvideFactory;
import com.kyusui.app.ui.customer.products.ProductDetailViewModel;
import com.kyusui.app.ui.customer.products.ProductDetailViewModel_HiltModules_KeyModule_ProvideFactory;
import com.kyusui.app.ui.customer.products.ProductListViewModel;
import com.kyusui.app.ui.customer.products.ProductListViewModel_HiltModules_KeyModule_ProvideFactory;
import dagger.hilt.android.ActivityRetainedLifecycle;
import dagger.hilt.android.ViewModelLifecycle;
import dagger.hilt.android.flags.HiltWrapper_FragmentGetContextFix_FragmentGetContextFixModule;
import dagger.hilt.android.internal.builders.ActivityComponentBuilder;
import dagger.hilt.android.internal.builders.ActivityRetainedComponentBuilder;
import dagger.hilt.android.internal.builders.FragmentComponentBuilder;
import dagger.hilt.android.internal.builders.ServiceComponentBuilder;
import dagger.hilt.android.internal.builders.ViewComponentBuilder;
import dagger.hilt.android.internal.builders.ViewModelComponentBuilder;
import dagger.hilt.android.internal.builders.ViewWithFragmentComponentBuilder;
import dagger.hilt.android.internal.lifecycle.DefaultViewModelFactories;
import dagger.hilt.android.internal.lifecycle.DefaultViewModelFactories_InternalFactoryFactory_Factory;
import dagger.hilt.android.internal.managers.ActivityRetainedComponentManager_LifecycleModule_ProvideActivityRetainedLifecycleFactory;
import dagger.hilt.android.internal.managers.SavedStateHandleHolder;
import dagger.hilt.android.internal.modules.ApplicationContextModule;
import dagger.hilt.android.internal.modules.ApplicationContextModule_ProvideContextFactory;
import dagger.internal.DaggerGenerated;
import dagger.internal.DoubleCheck;
import dagger.internal.MapBuilder;
import dagger.internal.Preconditions;
import dagger.internal.SetBuilder;
import java.util.Collections;
import java.util.Map;
import java.util.Set;
import javax.annotation.processing.Generated;
import javax.inject.Provider;
import kotlinx.serialization.json.Json;
import okhttp3.OkHttpClient;
import retrofit2.Retrofit;

@DaggerGenerated
@Generated(
    value = "dagger.internal.codegen.ComponentProcessor",
    comments = "https://dagger.dev"
)
@SuppressWarnings({
    "unchecked",
    "rawtypes",
    "KotlinInternal",
    "KotlinInternalInJava"
})
public final class DaggerKyusuiApplication_HiltComponents_SingletonC {
  private DaggerKyusuiApplication_HiltComponents_SingletonC() {
  }

  public static Builder builder() {
    return new Builder();
  }

  public static final class Builder {
    private ApplicationContextModule applicationContextModule;

    private Builder() {
    }

    public Builder applicationContextModule(ApplicationContextModule applicationContextModule) {
      this.applicationContextModule = Preconditions.checkNotNull(applicationContextModule);
      return this;
    }

    /**
     * @deprecated This module is declared, but an instance is not used in the component. This method is a no-op. For more, see https://dagger.dev/unused-modules.
     */
    @Deprecated
    public Builder contentModule(ContentModule contentModule) {
      Preconditions.checkNotNull(contentModule);
      return this;
    }

    /**
     * @deprecated This module is declared, but an instance is not used in the component. This method is a no-op. For more, see https://dagger.dev/unused-modules.
     */
    @Deprecated
    public Builder dataStoreProviderModule(DataStoreProviderModule dataStoreProviderModule) {
      Preconditions.checkNotNull(dataStoreProviderModule);
      return this;
    }

    /**
     * @deprecated This module is declared, but an instance is not used in the component. This method is a no-op. For more, see https://dagger.dev/unused-modules.
     */
    @Deprecated
    public Builder hiltWrapper_FragmentGetContextFix_FragmentGetContextFixModule(
        HiltWrapper_FragmentGetContextFix_FragmentGetContextFixModule hiltWrapper_FragmentGetContextFix_FragmentGetContextFixModule) {
      Preconditions.checkNotNull(hiltWrapper_FragmentGetContextFix_FragmentGetContextFixModule);
      return this;
    }

    /**
     * @deprecated This module is declared, but an instance is not used in the component. This method is a no-op. For more, see https://dagger.dev/unused-modules.
     */
    @Deprecated
    public Builder retrofitModule(RetrofitModule retrofitModule) {
      Preconditions.checkNotNull(retrofitModule);
      return this;
    }

    public KyusuiApplication_HiltComponents.SingletonC build() {
      Preconditions.checkBuilderRequirement(applicationContextModule, ApplicationContextModule.class);
      return new SingletonCImpl(applicationContextModule);
    }
  }

  private static final class ActivityRetainedCBuilder implements KyusuiApplication_HiltComponents.ActivityRetainedC.Builder {
    private final SingletonCImpl singletonCImpl;

    private SavedStateHandleHolder savedStateHandleHolder;

    private ActivityRetainedCBuilder(SingletonCImpl singletonCImpl) {
      this.singletonCImpl = singletonCImpl;
    }

    @Override
    public ActivityRetainedCBuilder savedStateHandleHolder(
        SavedStateHandleHolder savedStateHandleHolder) {
      this.savedStateHandleHolder = Preconditions.checkNotNull(savedStateHandleHolder);
      return this;
    }

    @Override
    public KyusuiApplication_HiltComponents.ActivityRetainedC build() {
      Preconditions.checkBuilderRequirement(savedStateHandleHolder, SavedStateHandleHolder.class);
      return new ActivityRetainedCImpl(singletonCImpl, savedStateHandleHolder);
    }
  }

  private static final class ActivityCBuilder implements KyusuiApplication_HiltComponents.ActivityC.Builder {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private Activity activity;

    private ActivityCBuilder(SingletonCImpl singletonCImpl,
        ActivityRetainedCImpl activityRetainedCImpl) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
    }

    @Override
    public ActivityCBuilder activity(Activity activity) {
      this.activity = Preconditions.checkNotNull(activity);
      return this;
    }

    @Override
    public KyusuiApplication_HiltComponents.ActivityC build() {
      Preconditions.checkBuilderRequirement(activity, Activity.class);
      return new ActivityCImpl(singletonCImpl, activityRetainedCImpl, activity);
    }
  }

  private static final class FragmentCBuilder implements KyusuiApplication_HiltComponents.FragmentC.Builder {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private final ActivityCImpl activityCImpl;

    private Fragment fragment;

    private FragmentCBuilder(SingletonCImpl singletonCImpl,
        ActivityRetainedCImpl activityRetainedCImpl, ActivityCImpl activityCImpl) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
      this.activityCImpl = activityCImpl;
    }

    @Override
    public FragmentCBuilder fragment(Fragment fragment) {
      this.fragment = Preconditions.checkNotNull(fragment);
      return this;
    }

    @Override
    public KyusuiApplication_HiltComponents.FragmentC build() {
      Preconditions.checkBuilderRequirement(fragment, Fragment.class);
      return new FragmentCImpl(singletonCImpl, activityRetainedCImpl, activityCImpl, fragment);
    }
  }

  private static final class ViewWithFragmentCBuilder implements KyusuiApplication_HiltComponents.ViewWithFragmentC.Builder {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private final ActivityCImpl activityCImpl;

    private final FragmentCImpl fragmentCImpl;

    private View view;

    private ViewWithFragmentCBuilder(SingletonCImpl singletonCImpl,
        ActivityRetainedCImpl activityRetainedCImpl, ActivityCImpl activityCImpl,
        FragmentCImpl fragmentCImpl) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
      this.activityCImpl = activityCImpl;
      this.fragmentCImpl = fragmentCImpl;
    }

    @Override
    public ViewWithFragmentCBuilder view(View view) {
      this.view = Preconditions.checkNotNull(view);
      return this;
    }

    @Override
    public KyusuiApplication_HiltComponents.ViewWithFragmentC build() {
      Preconditions.checkBuilderRequirement(view, View.class);
      return new ViewWithFragmentCImpl(singletonCImpl, activityRetainedCImpl, activityCImpl, fragmentCImpl, view);
    }
  }

  private static final class ViewCBuilder implements KyusuiApplication_HiltComponents.ViewC.Builder {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private final ActivityCImpl activityCImpl;

    private View view;

    private ViewCBuilder(SingletonCImpl singletonCImpl, ActivityRetainedCImpl activityRetainedCImpl,
        ActivityCImpl activityCImpl) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
      this.activityCImpl = activityCImpl;
    }

    @Override
    public ViewCBuilder view(View view) {
      this.view = Preconditions.checkNotNull(view);
      return this;
    }

    @Override
    public KyusuiApplication_HiltComponents.ViewC build() {
      Preconditions.checkBuilderRequirement(view, View.class);
      return new ViewCImpl(singletonCImpl, activityRetainedCImpl, activityCImpl, view);
    }
  }

  private static final class ViewModelCBuilder implements KyusuiApplication_HiltComponents.ViewModelC.Builder {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private SavedStateHandle savedStateHandle;

    private ViewModelLifecycle viewModelLifecycle;

    private ViewModelCBuilder(SingletonCImpl singletonCImpl,
        ActivityRetainedCImpl activityRetainedCImpl) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
    }

    @Override
    public ViewModelCBuilder savedStateHandle(SavedStateHandle handle) {
      this.savedStateHandle = Preconditions.checkNotNull(handle);
      return this;
    }

    @Override
    public ViewModelCBuilder viewModelLifecycle(ViewModelLifecycle viewModelLifecycle) {
      this.viewModelLifecycle = Preconditions.checkNotNull(viewModelLifecycle);
      return this;
    }

    @Override
    public KyusuiApplication_HiltComponents.ViewModelC build() {
      Preconditions.checkBuilderRequirement(savedStateHandle, SavedStateHandle.class);
      Preconditions.checkBuilderRequirement(viewModelLifecycle, ViewModelLifecycle.class);
      return new ViewModelCImpl(singletonCImpl, activityRetainedCImpl, savedStateHandle, viewModelLifecycle);
    }
  }

  private static final class ServiceCBuilder implements KyusuiApplication_HiltComponents.ServiceC.Builder {
    private final SingletonCImpl singletonCImpl;

    private Service service;

    private ServiceCBuilder(SingletonCImpl singletonCImpl) {
      this.singletonCImpl = singletonCImpl;
    }

    @Override
    public ServiceCBuilder service(Service service) {
      this.service = Preconditions.checkNotNull(service);
      return this;
    }

    @Override
    public KyusuiApplication_HiltComponents.ServiceC build() {
      Preconditions.checkBuilderRequirement(service, Service.class);
      return new ServiceCImpl(singletonCImpl, service);
    }
  }

  private static final class ViewWithFragmentCImpl extends KyusuiApplication_HiltComponents.ViewWithFragmentC {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private final ActivityCImpl activityCImpl;

    private final FragmentCImpl fragmentCImpl;

    private final ViewWithFragmentCImpl viewWithFragmentCImpl = this;

    private ViewWithFragmentCImpl(SingletonCImpl singletonCImpl,
        ActivityRetainedCImpl activityRetainedCImpl, ActivityCImpl activityCImpl,
        FragmentCImpl fragmentCImpl, View viewParam) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
      this.activityCImpl = activityCImpl;
      this.fragmentCImpl = fragmentCImpl;


    }
  }

  private static final class FragmentCImpl extends KyusuiApplication_HiltComponents.FragmentC {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private final ActivityCImpl activityCImpl;

    private final FragmentCImpl fragmentCImpl = this;

    private FragmentCImpl(SingletonCImpl singletonCImpl,
        ActivityRetainedCImpl activityRetainedCImpl, ActivityCImpl activityCImpl,
        Fragment fragmentParam) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
      this.activityCImpl = activityCImpl;


    }

    @Override
    public DefaultViewModelFactories.InternalFactoryFactory getHiltInternalFactoryFactory() {
      return activityCImpl.getHiltInternalFactoryFactory();
    }

    @Override
    public ViewWithFragmentComponentBuilder viewWithFragmentComponentBuilder() {
      return new ViewWithFragmentCBuilder(singletonCImpl, activityRetainedCImpl, activityCImpl, fragmentCImpl);
    }
  }

  private static final class ViewCImpl extends KyusuiApplication_HiltComponents.ViewC {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private final ActivityCImpl activityCImpl;

    private final ViewCImpl viewCImpl = this;

    private ViewCImpl(SingletonCImpl singletonCImpl, ActivityRetainedCImpl activityRetainedCImpl,
        ActivityCImpl activityCImpl, View viewParam) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
      this.activityCImpl = activityCImpl;


    }
  }

  private static final class ActivityCImpl extends KyusuiApplication_HiltComponents.ActivityC {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private final ActivityCImpl activityCImpl = this;

    private ActivityCImpl(SingletonCImpl singletonCImpl,
        ActivityRetainedCImpl activityRetainedCImpl, Activity activityParam) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;


    }

    @Override
    public void injectMainActivity(MainActivity arg0) {
    }

    @Override
    public DefaultViewModelFactories.InternalFactoryFactory getHiltInternalFactoryFactory() {
      return DefaultViewModelFactories_InternalFactoryFactory_Factory.newInstance(getViewModelKeys(), new ViewModelCBuilder(singletonCImpl, activityRetainedCImpl));
    }

    @Override
    public Set<String> getViewModelKeys() {
      return SetBuilder.<String>newSetBuilder(8).add(AuthViewModel_HiltModules_KeyModule_ProvideFactory.provide()).add(CartViewModel_HiltModules_KeyModule_ProvideFactory.provide()).add(CreateOrderViewModel_HiltModules_KeyModule_ProvideFactory.provide()).add(OrderDetailViewModel_HiltModules_KeyModule_ProvideFactory.provide()).add(OrderHistoryViewModel_HiltModules_KeyModule_ProvideFactory.provide()).add(PaymentViewModel_HiltModules_KeyModule_ProvideFactory.provide()).add(ProductDetailViewModel_HiltModules_KeyModule_ProvideFactory.provide()).add(ProductListViewModel_HiltModules_KeyModule_ProvideFactory.provide()).build();
    }

    @Override
    public ViewModelComponentBuilder getViewModelComponentBuilder() {
      return new ViewModelCBuilder(singletonCImpl, activityRetainedCImpl);
    }

    @Override
    public FragmentComponentBuilder fragmentComponentBuilder() {
      return new FragmentCBuilder(singletonCImpl, activityRetainedCImpl, activityCImpl);
    }

    @Override
    public ViewComponentBuilder viewComponentBuilder() {
      return new ViewCBuilder(singletonCImpl, activityRetainedCImpl, activityCImpl);
    }
  }

  private static final class ViewModelCImpl extends KyusuiApplication_HiltComponents.ViewModelC {
    private final SavedStateHandle savedStateHandle;

    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl;

    private final ViewModelCImpl viewModelCImpl = this;

    private Provider<AuthViewModel> authViewModelProvider;

    private Provider<CartViewModel> cartViewModelProvider;

    private Provider<CreateOrderViewModel> createOrderViewModelProvider;

    private Provider<OrderDetailViewModel> orderDetailViewModelProvider;

    private Provider<OrderHistoryViewModel> orderHistoryViewModelProvider;

    private Provider<PaymentViewModel> paymentViewModelProvider;

    private Provider<ProductDetailViewModel> productDetailViewModelProvider;

    private Provider<ProductListViewModel> productListViewModelProvider;

    private ViewModelCImpl(SingletonCImpl singletonCImpl,
        ActivityRetainedCImpl activityRetainedCImpl, SavedStateHandle savedStateHandleParam,
        ViewModelLifecycle viewModelLifecycleParam) {
      this.singletonCImpl = singletonCImpl;
      this.activityRetainedCImpl = activityRetainedCImpl;
      this.savedStateHandle = savedStateHandleParam;
      initialize(savedStateHandleParam, viewModelLifecycleParam);

    }

    @SuppressWarnings("unchecked")
    private void initialize(final SavedStateHandle savedStateHandleParam,
        final ViewModelLifecycle viewModelLifecycleParam) {
      this.authViewModelProvider = new SwitchingProvider<>(singletonCImpl, activityRetainedCImpl, viewModelCImpl, 0);
      this.cartViewModelProvider = new SwitchingProvider<>(singletonCImpl, activityRetainedCImpl, viewModelCImpl, 1);
      this.createOrderViewModelProvider = new SwitchingProvider<>(singletonCImpl, activityRetainedCImpl, viewModelCImpl, 2);
      this.orderDetailViewModelProvider = new SwitchingProvider<>(singletonCImpl, activityRetainedCImpl, viewModelCImpl, 3);
      this.orderHistoryViewModelProvider = new SwitchingProvider<>(singletonCImpl, activityRetainedCImpl, viewModelCImpl, 4);
      this.paymentViewModelProvider = new SwitchingProvider<>(singletonCImpl, activityRetainedCImpl, viewModelCImpl, 5);
      this.productDetailViewModelProvider = new SwitchingProvider<>(singletonCImpl, activityRetainedCImpl, viewModelCImpl, 6);
      this.productListViewModelProvider = new SwitchingProvider<>(singletonCImpl, activityRetainedCImpl, viewModelCImpl, 7);
    }

    @Override
    public Map<String, Provider<ViewModel>> getHiltViewModelMap() {
      return MapBuilder.<String, Provider<ViewModel>>newMapBuilder(8).put("com.kyusui.app.ui.auth.AuthViewModel", ((Provider) authViewModelProvider)).put("com.kyusui.app.ui.customer.createorder.CartViewModel", ((Provider) cartViewModelProvider)).put("com.kyusui.app.ui.customer.createorder.CreateOrderViewModel", ((Provider) createOrderViewModelProvider)).put("com.kyusui.app.ui.customer.orders.OrderDetailViewModel", ((Provider) orderDetailViewModelProvider)).put("com.kyusui.app.ui.customer.orders.OrderHistoryViewModel", ((Provider) orderHistoryViewModelProvider)).put("com.kyusui.app.ui.customer.payment.PaymentViewModel", ((Provider) paymentViewModelProvider)).put("com.kyusui.app.ui.customer.products.ProductDetailViewModel", ((Provider) productDetailViewModelProvider)).put("com.kyusui.app.ui.customer.products.ProductListViewModel", ((Provider) productListViewModelProvider)).build();
    }

    @Override
    public Map<String, Object> getHiltViewModelAssistedMap() {
      return Collections.<String, Object>emptyMap();
    }

    private static final class SwitchingProvider<T> implements Provider<T> {
      private final SingletonCImpl singletonCImpl;

      private final ActivityRetainedCImpl activityRetainedCImpl;

      private final ViewModelCImpl viewModelCImpl;

      private final int id;

      SwitchingProvider(SingletonCImpl singletonCImpl, ActivityRetainedCImpl activityRetainedCImpl,
          ViewModelCImpl viewModelCImpl, int id) {
        this.singletonCImpl = singletonCImpl;
        this.activityRetainedCImpl = activityRetainedCImpl;
        this.viewModelCImpl = viewModelCImpl;
        this.id = id;
      }

      @SuppressWarnings("unchecked")
      @Override
      public T get() {
        switch (id) {
          case 0: // com.kyusui.app.ui.auth.AuthViewModel 
          return (T) new AuthViewModel(singletonCImpl.authRepositoryImplProvider.get());

          case 1: // com.kyusui.app.ui.customer.createorder.CartViewModel 
          return (T) new CartViewModel();

          case 2: // com.kyusui.app.ui.customer.createorder.CreateOrderViewModel 
          return (T) new CreateOrderViewModel(singletonCImpl.customerRepositoryImplProvider.get());

          case 3: // com.kyusui.app.ui.customer.orders.OrderDetailViewModel 
          return (T) new OrderDetailViewModel(singletonCImpl.customerRepositoryImplProvider.get(), viewModelCImpl.savedStateHandle);

          case 4: // com.kyusui.app.ui.customer.orders.OrderHistoryViewModel 
          return (T) new OrderHistoryViewModel(singletonCImpl.customerRepositoryImplProvider.get());

          case 5: // com.kyusui.app.ui.customer.payment.PaymentViewModel 
          return (T) new PaymentViewModel(viewModelCImpl.savedStateHandle, singletonCImpl.customerRepositoryImplProvider.get(), singletonCImpl.contentResolverProofFileSourceProvider.get());

          case 6: // com.kyusui.app.ui.customer.products.ProductDetailViewModel 
          return (T) new ProductDetailViewModel(viewModelCImpl.savedStateHandle);

          case 7: // com.kyusui.app.ui.customer.products.ProductListViewModel 
          return (T) new ProductListViewModel(singletonCImpl.customerRepositoryImplProvider.get());

          default: throw new AssertionError(id);
        }
      }
    }
  }

  private static final class ActivityRetainedCImpl extends KyusuiApplication_HiltComponents.ActivityRetainedC {
    private final SingletonCImpl singletonCImpl;

    private final ActivityRetainedCImpl activityRetainedCImpl = this;

    private Provider<ActivityRetainedLifecycle> provideActivityRetainedLifecycleProvider;

    private ActivityRetainedCImpl(SingletonCImpl singletonCImpl,
        SavedStateHandleHolder savedStateHandleHolderParam) {
      this.singletonCImpl = singletonCImpl;

      initialize(savedStateHandleHolderParam);

    }

    @SuppressWarnings("unchecked")
    private void initialize(final SavedStateHandleHolder savedStateHandleHolderParam) {
      this.provideActivityRetainedLifecycleProvider = DoubleCheck.provider(new SwitchingProvider<ActivityRetainedLifecycle>(singletonCImpl, activityRetainedCImpl, 0));
    }

    @Override
    public ActivityComponentBuilder activityComponentBuilder() {
      return new ActivityCBuilder(singletonCImpl, activityRetainedCImpl);
    }

    @Override
    public ActivityRetainedLifecycle getActivityRetainedLifecycle() {
      return provideActivityRetainedLifecycleProvider.get();
    }

    private static final class SwitchingProvider<T> implements Provider<T> {
      private final SingletonCImpl singletonCImpl;

      private final ActivityRetainedCImpl activityRetainedCImpl;

      private final int id;

      SwitchingProvider(SingletonCImpl singletonCImpl, ActivityRetainedCImpl activityRetainedCImpl,
          int id) {
        this.singletonCImpl = singletonCImpl;
        this.activityRetainedCImpl = activityRetainedCImpl;
        this.id = id;
      }

      @SuppressWarnings("unchecked")
      @Override
      public T get() {
        switch (id) {
          case 0: // dagger.hilt.android.ActivityRetainedLifecycle 
          return (T) ActivityRetainedComponentManager_LifecycleModule_ProvideActivityRetainedLifecycleFactory.provideActivityRetainedLifecycle();

          default: throw new AssertionError(id);
        }
      }
    }
  }

  private static final class ServiceCImpl extends KyusuiApplication_HiltComponents.ServiceC {
    private final SingletonCImpl singletonCImpl;

    private final ServiceCImpl serviceCImpl = this;

    private ServiceCImpl(SingletonCImpl singletonCImpl, Service serviceParam) {
      this.singletonCImpl = singletonCImpl;


    }
  }

  private static final class SingletonCImpl extends KyusuiApplication_HiltComponents.SingletonC {
    private final ApplicationContextModule applicationContextModule;

    private final SingletonCImpl singletonCImpl = this;

    private Provider<DataStore<Preferences>> providePreferencesDataStoreProvider;

    private Provider<PreferencesRepository> preferencesRepositoryProvider;

    private Provider<AuthTokenStore> provideAuthTokenStoreProvider;

    private Provider<AuthInterceptor> provideAuthInterceptorProvider;

    private Provider<Json> provideJsonProvider;

    private Provider<ApiErrorParser> provideApiErrorParserProvider;

    private Provider<OkHttpClient> provideOkHttpClientProvider;

    private Provider<ImageLoader> provideImageLoaderProvider;

    private Provider<Retrofit> provideRetrofitProvider;

    private Provider<AuthApi> provideAuthApiProvider;

    private Provider<AuthRepositoryImpl> authRepositoryImplProvider;

    private Provider<CustomerApi> provideCustomerApiProvider;

    private Provider<ContentResolver> provideContentResolverProvider;

    private Provider<ContentResolverProofFileSource> contentResolverProofFileSourceProvider;

    private Provider<CustomerRepositoryImpl> customerRepositoryImplProvider;

    private SingletonCImpl(ApplicationContextModule applicationContextModuleParam) {
      this.applicationContextModule = applicationContextModuleParam;
      initialize(applicationContextModuleParam);

    }

    @SuppressWarnings("unchecked")
    private void initialize(final ApplicationContextModule applicationContextModuleParam) {
      this.providePreferencesDataStoreProvider = DoubleCheck.provider(new SwitchingProvider<DataStore<Preferences>>(singletonCImpl, 5));
      this.preferencesRepositoryProvider = DoubleCheck.provider(new SwitchingProvider<PreferencesRepository>(singletonCImpl, 4));
      this.provideAuthTokenStoreProvider = DoubleCheck.provider(new SwitchingProvider<AuthTokenStore>(singletonCImpl, 3));
      this.provideAuthInterceptorProvider = DoubleCheck.provider(new SwitchingProvider<AuthInterceptor>(singletonCImpl, 2));
      this.provideJsonProvider = DoubleCheck.provider(new SwitchingProvider<Json>(singletonCImpl, 7));
      this.provideApiErrorParserProvider = DoubleCheck.provider(new SwitchingProvider<ApiErrorParser>(singletonCImpl, 6));
      this.provideOkHttpClientProvider = DoubleCheck.provider(new SwitchingProvider<OkHttpClient>(singletonCImpl, 1));
      this.provideImageLoaderProvider = DoubleCheck.provider(new SwitchingProvider<ImageLoader>(singletonCImpl, 0));
      this.provideRetrofitProvider = DoubleCheck.provider(new SwitchingProvider<Retrofit>(singletonCImpl, 10));
      this.provideAuthApiProvider = DoubleCheck.provider(new SwitchingProvider<AuthApi>(singletonCImpl, 9));
      this.authRepositoryImplProvider = DoubleCheck.provider(new SwitchingProvider<AuthRepositoryImpl>(singletonCImpl, 8));
      this.provideCustomerApiProvider = DoubleCheck.provider(new SwitchingProvider<CustomerApi>(singletonCImpl, 12));
      this.provideContentResolverProvider = DoubleCheck.provider(new SwitchingProvider<ContentResolver>(singletonCImpl, 14));
      this.contentResolverProofFileSourceProvider = DoubleCheck.provider(new SwitchingProvider<ContentResolverProofFileSource>(singletonCImpl, 13));
      this.customerRepositoryImplProvider = DoubleCheck.provider(new SwitchingProvider<CustomerRepositoryImpl>(singletonCImpl, 11));
    }

    @Override
    public void injectKyusuiApplication(KyusuiApplication arg0) {
    }

    @Override
    public ImageLoader imageLoader() {
      return provideImageLoaderProvider.get();
    }

    @Override
    public Set<Boolean> getDisableFragmentGetContextFix() {
      return Collections.<Boolean>emptySet();
    }

    @Override
    public ActivityRetainedComponentBuilder retainedComponentBuilder() {
      return new ActivityRetainedCBuilder(singletonCImpl);
    }

    @Override
    public ServiceComponentBuilder serviceComponentBuilder() {
      return new ServiceCBuilder(singletonCImpl);
    }

    private static final class SwitchingProvider<T> implements Provider<T> {
      private final SingletonCImpl singletonCImpl;

      private final int id;

      SwitchingProvider(SingletonCImpl singletonCImpl, int id) {
        this.singletonCImpl = singletonCImpl;
        this.id = id;
      }

      @SuppressWarnings("unchecked")
      @Override
      public T get() {
        switch (id) {
          case 0: // coil.ImageLoader 
          return (T) RetrofitModule_ProvideImageLoaderFactory.provideImageLoader(ApplicationContextModule_ProvideContextFactory.provideContext(singletonCImpl.applicationContextModule), singletonCImpl.provideOkHttpClientProvider.get());

          case 1: // okhttp3.OkHttpClient 
          return (T) RetrofitModule_ProvideOkHttpClientFactory.provideOkHttpClient(singletonCImpl.provideAuthInterceptorProvider.get(), singletonCImpl.provideApiErrorParserProvider.get());

          case 2: // com.kyusui.app.data.api.AuthInterceptor 
          return (T) RetrofitModule_ProvideAuthInterceptorFactory.provideAuthInterceptor(singletonCImpl.provideAuthTokenStoreProvider.get());

          case 3: // com.kyusui.app.data.api.AuthTokenStore 
          return (T) RetrofitModule_ProvideAuthTokenStoreFactory.provideAuthTokenStore(singletonCImpl.preferencesRepositoryProvider.get());

          case 4: // com.kyusui.app.data.datastore.PreferencesRepository 
          return (T) new PreferencesRepository(singletonCImpl.providePreferencesDataStoreProvider.get());

          case 5: // androidx.datastore.core.DataStore<androidx.datastore.preferences.core.Preferences> 
          return (T) DataStoreProviderModule_ProvidePreferencesDataStoreFactory.providePreferencesDataStore(ApplicationContextModule_ProvideContextFactory.provideContext(singletonCImpl.applicationContextModule));

          case 6: // com.kyusui.app.data.api.ApiErrorParser 
          return (T) RetrofitModule_ProvideApiErrorParserFactory.provideApiErrorParser(singletonCImpl.provideJsonProvider.get());

          case 7: // kotlinx.serialization.json.Json 
          return (T) RetrofitModule_ProvideJsonFactory.provideJson();

          case 8: // com.kyusui.app.data.repository.AuthRepositoryImpl 
          return (T) new AuthRepositoryImpl(singletonCImpl.provideAuthApiProvider.get(), singletonCImpl.preferencesRepositoryProvider.get());

          case 9: // com.kyusui.app.data.api.AuthApi 
          return (T) RetrofitModule_ProvideAuthApiFactory.provideAuthApi(singletonCImpl.provideRetrofitProvider.get());

          case 10: // retrofit2.Retrofit 
          return (T) RetrofitModule_ProvideRetrofitFactory.provideRetrofit(singletonCImpl.provideOkHttpClientProvider.get(), singletonCImpl.provideJsonProvider.get());

          case 11: // com.kyusui.app.data.repository.CustomerRepositoryImpl 
          return (T) new CustomerRepositoryImpl(singletonCImpl.provideCustomerApiProvider.get(), singletonCImpl.contentResolverProofFileSourceProvider.get());

          case 12: // com.kyusui.app.data.api.CustomerApi 
          return (T) RetrofitModule_ProvideCustomerApiFactory.provideCustomerApi(singletonCImpl.provideRetrofitProvider.get());

          case 13: // com.kyusui.app.data.upload.ContentResolverProofFileSource 
          return (T) new ContentResolverProofFileSource(singletonCImpl.provideContentResolverProvider.get());

          case 14: // android.content.ContentResolver 
          return (T) ContentModule_ProvideContentResolverFactory.provideContentResolver(ApplicationContextModule_ProvideContextFactory.provideContext(singletonCImpl.applicationContextModule));

          default: throw new AssertionError(id);
        }
      }
    }
  }
}
