package com.kyusui.app.data.api;

import com.kyusui.app.data.datastore.PreferencesDataSource;
import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.Preconditions;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import javax.inject.Provider;

@ScopeMetadata("javax.inject.Singleton")
@QualifierMetadata
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
public final class RetrofitModule_ProvideAuthTokenStoreFactory implements Factory<AuthTokenStore> {
  private final Provider<PreferencesDataSource> preferencesRepositoryProvider;

  public RetrofitModule_ProvideAuthTokenStoreFactory(
      Provider<PreferencesDataSource> preferencesRepositoryProvider) {
    this.preferencesRepositoryProvider = preferencesRepositoryProvider;
  }

  @Override
  public AuthTokenStore get() {
    return provideAuthTokenStore(preferencesRepositoryProvider.get());
  }

  public static RetrofitModule_ProvideAuthTokenStoreFactory create(
      Provider<PreferencesDataSource> preferencesRepositoryProvider) {
    return new RetrofitModule_ProvideAuthTokenStoreFactory(preferencesRepositoryProvider);
  }

  public static AuthTokenStore provideAuthTokenStore(PreferencesDataSource preferencesRepository) {
    return Preconditions.checkNotNullFromProvides(RetrofitModule.INSTANCE.provideAuthTokenStore(preferencesRepository));
  }
}
